'use client';

import {useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {
    BookOpenIcon,
    ExpandIcon,
    MoreVerticalIcon,
    PinOffIcon,
} from 'lucide-react';
import type {Asset} from '@/types/api';
import {searchAssets} from '@/lib/api/assets';
import {PanelSection} from '@/components/layout/PanelSection';
import {Button} from '@/components/ui/button';
import {Skeleton} from '@/components/ui/misc';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuTrigger,
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/menu';
import {AssetThumb} from '@/features/assets/list/AssetThumb';
import {useLiveAsset} from '@/features/assets/assetStore';
import {useAssetOpener} from '@/features/assets/useAssetOpener';
import {useDropTarget} from '@/features/dnd/useDropTarget';
import {cn} from '@/lib/utils/cn';
import {usePinnedStoriesStore} from './pinnedStoriesStore';

/**
 * The stories pinned by the user (see `usePinnedStoriesStore`): a shelf in
 * the sidebar, each row being a drop target that adds assets to the story.
 */
export function PinnedStoriesPanel() {
    const {t} = useTranslation();
    const ids = usePinnedStoriesStore(s => s.ids);
    const stories = useQuery({
        queryKey: ['pinned-stories', ids],
        queryFn: () => searchAssets({ids, limit: ids.length}),
        enabled: ids.length > 0,
        staleTime: 60_000,
    });
    const byId = useMemo(() => {
        const map = new Map<string, Asset>();
        stories.data?.items.forEach(a => map.set(a.id, a));

        return map;
    }, [stories.data]);

    if (ids.length === 0) {
        return null;
    }

    return (
        <PanelSection
            title={t('story.pinned.title', 'Pinned stories')}
            icon={<BookOpenIcon />}
            defaultOpen
        >
            {stories.isLoading ? (
                <div className="space-y-1 px-3 py-1">
                    <Skeleton className="h-7 w-2/3" />
                    <Skeleton className="h-7 w-1/2" />
                </div>
            ) : (
                <ul data-testid="pinned-stories">
                    {[...ids].reverse().map(id => {
                        const story = byId.get(id);

                        return story ? (
                            <PinnedStoryRow key={id} story={story} />
                        ) : (
                            <UnavailableStoryRow key={id} id={id} />
                        );
                    })}
                </ul>
            )}
        </PanelSection>
    );
}

function PinnedStoryRow({story: initial}: {story: Asset}) {
    const {t} = useTranslation();
    const story = useLiveAsset(initial);
    const openAsset = useAssetOpener();
    const unpin = usePinnedStoriesStore(s => s.unpin);
    const drop = useDropTarget({type: 'story', story});
    const name = story.name || t('story.untitled', 'Untitled story');

    const menu = (
        Item: typeof DropdownMenuItem,
        Sep: typeof DropdownMenuSeparator
    ) => (
        <>
            <Item onSelect={() => openAsset(story)}>
                <ExpandIcon /> {t('common.open', 'Open')}
            </Item>
            <Sep />
            <Item onSelect={() => unpin(story.id)}>
                <PinOffIcon /> {t('story.unpin', 'Unpin story')}
            </Item>
        </>
    );

    return (
        <ContextMenu>
            <ContextMenuTrigger asChild>
                <li
                    ref={drop.setNodeRef}
                    data-testid="pinned-story-item"
                    data-story-id={story.id}
                    className={cn(
                        'group/story flex items-center gap-1 px-2',
                        'data-[state=open]:bg-accent has-[>[data-state=open]]:bg-accent',
                        drop.dropClass
                    )}
                >
                    <button
                        type="button"
                        className="flex min-w-0 flex-1 items-center gap-2 rounded px-1 py-1 text-left text-sm hover:bg-accent"
                        onClick={() => openAsset(story)}
                        title={name}
                    >
                        <span className="size-7 shrink-0 overflow-hidden rounded border bg-media-bg">
                            <AssetThumb asset={story} size={28} />
                        </span>
                        <span className="truncate">{name}</span>
                    </button>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                className="opacity-0 group-hover/story:opacity-100 data-[state=open]:opacity-100"
                            >
                                <MoreVerticalIcon />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {menu(DropdownMenuItem, DropdownMenuSeparator)}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </li>
            </ContextMenuTrigger>
            <ContextMenuContent>
                {menu(ContextMenuItem as any, ContextMenuSeparator as any)}
            </ContextMenuContent>
        </ContextMenu>
    );
}

/** A pinned story that can no longer be read (deleted, access revoked…) */
function UnavailableStoryRow({id}: {id: string}) {
    const {t} = useTranslation();
    const unpin = usePinnedStoriesStore(s => s.unpin);

    return (
        <li
            data-testid="pinned-story-item"
            data-story-id={id}
            className="group/story flex items-center gap-1 px-2 text-muted-foreground"
        >
            <span className="flex min-w-0 flex-1 items-center gap-2 px-1 py-1 text-sm italic">
                <span className="flex size-7 shrink-0 items-center justify-center rounded border">
                    <BookOpenIcon className="size-4" />
                </span>
                <span className="truncate">
                    {t('story.unavailable', 'Unavailable story')}
                </span>
            </span>
            <Button
                variant="ghost"
                size="icon-xs"
                className="opacity-0 group-hover/story:opacity-100"
                aria-label={t('story.unpin', 'Unpin story')}
                onClick={() => unpin(id)}
            >
                <PinOffIcon />
            </Button>
        </li>
    );
}
