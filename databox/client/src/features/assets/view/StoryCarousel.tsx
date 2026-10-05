'use client';

import {useMemo, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    InfiniteData,
    useInfiniteQuery,
    useQueryClient,
} from '@tanstack/react-query';
import {
    ArrowLeftToLineIcon,
    ArrowRightToLineIcon,
    LayersIcon,
    UnlinkIcon,
} from 'lucide-react';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    horizontalListSortingStrategy,
    SortableContext,
} from '@dnd-kit/sortable';
import {toast} from 'sonner';
import type {Asset} from '@/types/api';
import {EntityName} from '@/types/api';
import {
    removeAssetFromCollection,
    searchAssets,
    SearchAssetsResult,
    setAssetPosition,
} from '@/lib/api/assets';
import {AssetThumb} from '@/features/assets/list/AssetThumb';
import {AssetMenuItems} from '@/features/assets/list/AssetContextMenu';
import {Button} from '@/components/ui/button';
import {Skeleton} from '@/components/ui/misc';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuTrigger,
} from '@/components/ui/menu';
import {
    overlayRow,
    SortableOverlay,
    sortableMeasuring,
    SortableRow,
    useSortableRow,
} from '@/components/ui/sortable';
import {iri} from '@/lib/utils/iri';
import {toastError} from '@/lib/utils/errors';
import {cn} from '@/lib/utils/cn';

/**
 * Assets contained in a story, in their stored order. Shared by the carousel
 * and the viewer (which falls back to the first item when the story itself
 * has no rendition): same query key, one request.
 */
export function useStoryAssets(storyId?: string) {
    const query = useInfiniteQuery({
        queryKey: storyAssetsKey(storyId),
        queryFn: ({pageParam}) =>
            searchAssets({
                url: pageParam,
                story: storyId!,
                order: {'@position': 'asc'},
                limit: 30,
            }),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        enabled: !!storyId,
    });
    const pages = query.data?.pages;
    const items = useMemo(() => pages?.flatMap(p => p.items) ?? [], [pages]);

    return {...query, items, total: pages?.[0]?.total ?? 0};
}

function storyAssetsKey(storyId?: string) {
    return ['story-assets', storyId];
}

type StoryPages = InfiniteData<SearchAssetsResult, string | undefined>;

/**
 * Rewrites the loaded items of a story, the pages keeping their size: the
 * index is re-read by the search asynchronously, refetching would bring the
 * previous order back.
 */
export function rewriteItems(
    data: StoryPages | undefined,
    rewrite: (items: Asset[]) => Asset[],
    removed: number
): StoryPages | undefined {
    if (!data) {
        return data;
    }
    const after = rewrite(data.pages.flatMap(p => p.items));
    let offset = 0;

    return {
        ...data,
        pages: data.pages.map((p, i) => {
            const size =
                i === data.pages.length - 1
                    ? after.length - offset
                    : p.items.length;
            const items = after.slice(offset, offset + size);
            offset += size;

            return {...p, items, total: p.total - removed};
        }),
    };
}

/**
 * Horizontal strip of the assets contained in a story.
 *
 * Selecting one switches the asset displayed by the viewer in place
 * (`onSelect`) instead of navigating: the story context — this strip and the
 * previous / next navigation inside the story — is kept.
 *
 * Who may edit the story reorders its items by dragging them, and moves or
 * removes them from their context menu.
 */
export function StoryCarousel({
    story,
    currentAssetId,
    onSelect,
}: {
    story: Asset;
    /** The asset currently displayed, the story itself or one of its items */
    currentAssetId: string;
    onSelect: (assetId: string) => void;
}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const storyId = story.id;
    const query = useStoryAssets(storyId);
    const {items: loaded, total} = query;
    // The query cache notifies its observers asynchronously: a drop reorders
    // the strip in its own render, or the drop animation would measure the
    // item at its former place and fly back there
    const [dropped, setDropped] = useState<{of: Asset[]; items: Asset[]}>();
    const items = dropped?.of === loaded ? dropped.items : loaded;
    const storyCollectionId = story.storyCollection?.id;
    const editable = !!story.capabilities.edit && !!storyCollectionId;

    const sensors = useSensors(
        useSensor(PointerSensor, {activationConstraint: {distance: 6}})
    );
    // The click ending a drag must not select the dropped item
    const justDragged = useRef(false);

    const updateCache = (rewrite: (items: Asset[]) => Asset[], removed = 0) =>
        queryClient.setQueryData<StoryPages>(storyAssetsKey(storyId), data =>
            rewriteItems(data, rewrite, removed)
        );
    const restore = (e: unknown) => {
        toastError(e);
        void queryClient.invalidateQueries({
            queryKey: storyAssetsKey(storyId),
        });
    };

    const move = (assetId: string, position: number) => {
        const from = items.findIndex(a => a.id === assetId);
        if (from < 0 || from === position) {
            return;
        }
        updateCache(list =>
            position < list.length
                ? arrayMove(list, from, position)
                : // Beyond the loaded pages: shows up when they are
                  list.filter(a => a.id !== assetId)
        );
        setAssetPosition(
            assetId,
            iri(EntityName.Asset, storyId),
            position
        ).catch(restore);
    };

    const remove = (asset: Asset) => {
        updateCache(list => list.filter(a => a.id !== asset.id), 1);
        if (asset.id === currentAssetId) {
            onSelect(storyId);
        }
        removeAssetFromCollection(asset.id, storyCollectionId!)
            .then(() => {
                void queryClient.invalidateQueries({
                    queryKey: ['story-thumbnails', storyId],
                });
                toast.success(
                    t('story.asset_removed', '{{name}} removed from story', {
                        name: asset.name ?? '',
                    })
                );
            })
            .catch(restore);
    };

    const onDragEnd = ({active, over}: DragEndEvent) => {
        justDragged.current = true;
        setTimeout(() => (justDragged.current = false));
        if (!over || active.id === over.id) {
            return;
        }
        const from = items.findIndex(a => a.id === active.id);
        const to = items.findIndex(a => a.id === over.id);
        setDropped({of: loaded, items: arrayMove(items, from, to)});
        move(String(active.id), to);
    };

    const selectItem = (id: string) => {
        if (!justDragged.current) {
            onSelect(id);
        }
    };

    return (
        <div
            data-testid="story-carousel"
            className="flex h-28 shrink-0 items-center gap-2 overflow-x-auto border-t bg-background px-3 py-2"
        >
            <span className="mr-1 text-xs font-medium text-muted-foreground">
                {t('asset.story.items', '{{count}} items', {count: total})}
            </span>
            <button
                type="button"
                data-testid="story-cover"
                aria-current={currentAssetId === storyId}
                className={cn(
                    'flex size-20 shrink-0 flex-col items-center justify-center gap-1 rounded-md border bg-muted/40 px-1 text-[10px] text-muted-foreground hover:ring-2 hover:ring-primary',
                    currentAssetId === storyId && 'ring-2 ring-primary'
                )}
                onClick={() => onSelect(storyId)}
                title={t('asset.story.cover', 'Story')}
            >
                <LayersIcon className="size-5" />
                {t('asset.story.cover', 'Story')}
            </button>
            {query.isLoading
                ? [...Array(6)].map((_, i) => (
                      <Skeleton key={i} className="size-20 shrink-0" />
                  ))
                : null}
            <DndContext
                measuring={sortableMeasuring}
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={onDragEnd}
            >
                <SortableContext
                    items={items.map(a => a.id)}
                    strategy={horizontalListSortingStrategy}
                    disabled={!editable}
                >
                    {items.map((child, index) => (
                        <ContextMenu key={child.id}>
                            <ContextMenuTrigger asChild>
                                <div className="shrink-0">
                                    <SortableStoryItem
                                        asset={child}
                                        current={child.id === currentAssetId}
                                        onClick={() => selectItem(child.id)}
                                    />
                                </div>
                            </ContextMenuTrigger>
                            <ContextMenuContent className="w-56">
                                {editable ? (
                                    <>
                                        <ContextMenuItem
                                            data-testid="story-item-move-first"
                                            disabled={index === 0}
                                            onSelect={() => move(child.id, 0)}
                                        >
                                            <ArrowLeftToLineIcon />
                                            {t(
                                                'story.item.move_first',
                                                'Move to start'
                                            )}
                                        </ContextMenuItem>
                                        <ContextMenuItem
                                            data-testid="story-item-move-last"
                                            disabled={index === total - 1}
                                            onSelect={() =>
                                                move(child.id, total - 1)
                                            }
                                        >
                                            <ArrowRightToLineIcon />
                                            {t(
                                                'story.item.move_last',
                                                'Move to end'
                                            )}
                                        </ContextMenuItem>
                                        <ContextMenuItem
                                            data-testid="story-item-remove"
                                            variant="destructive"
                                            disabled={
                                                child.referenceCollection
                                                    ?.id === storyCollectionId
                                            }
                                            onSelect={() => remove(child)}
                                        >
                                            <UnlinkIcon />
                                            {t(
                                                'story.item.remove',
                                                'Remove from story'
                                            )}
                                        </ContextMenuItem>
                                        <ContextMenuSeparator />
                                    </>
                                ) : null}
                                <AssetMenuItems
                                    assets={[child]}
                                    variant="context"
                                    onOpen={() => onSelect(child.id)}
                                />
                            </ContextMenuContent>
                        </ContextMenu>
                    ))}
                </SortableContext>
                <SortableOverlay>
                    {id => {
                        const child = items.find(a => a.id === id);

                        return child ? (
                            <StoryItem
                                asset={child}
                                current={child.id === currentAssetId}
                                drag={overlayRow}
                                onClick={() => undefined}
                            />
                        ) : null;
                    }}
                </SortableOverlay>
            </DndContext>
            {query.hasNextPage ? (
                <Button
                    variant="outline"
                    size="sm"
                    className="shrink-0"
                    onClick={() => query.fetchNextPage()}
                    loading={query.isFetchingNextPage}
                >
                    +{total - items.length} {t('common.more', 'more')}
                </Button>
            ) : null}
        </div>
    );
}

type StoryItemProps = {
    asset: Asset;
    current: boolean;
    drag: SortableRow;
    onClick: () => void;
};

function SortableStoryItem(props: Omit<StoryItemProps, 'drag'>) {
    const drag = useSortableRow(props.asset.id);

    return <StoryItem {...props} drag={drag} />;
}

/** An item of the strip, also rendered as the copy following the pointer */
function StoryItem({asset, current, drag, onClick}: StoryItemProps) {
    return (
        <button
            ref={drag.nodeRef}
            type="button"
            data-testid="story-item"
            aria-current={current}
            style={drag.style}
            {...drag.handle}
            className={cn(
                'block size-20 overflow-hidden rounded-md border bg-media-bg hover:ring-2 hover:ring-primary',
                current && 'ring-2 ring-primary',
                drag.className
            )}
            onClick={onClick}
            title={asset.name}
        >
            <AssetThumb asset={asset} size={80} />
        </button>
    );
}
