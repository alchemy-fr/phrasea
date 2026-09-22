'use client';

import {useMemo} from 'react';
import {useTranslation} from 'react-i18next';
import {useInfiniteQuery} from '@tanstack/react-query';
import {LayersIcon} from 'lucide-react';
import {searchAssets} from '@/lib/api/assets';
import {AssetThumb} from '@/features/assets/list/AssetThumb';
import {Button} from '@/components/ui/button';
import {Skeleton} from '@/components/ui/misc';
import {cn} from '@/lib/utils/cn';

/**
 * Assets contained in a story. Shared by the carousel and the viewer (which
 * falls back to the first item when the story itself has no rendition): same
 * query key, one request.
 */
export function useStoryAssets(storyId?: string) {
    const query = useInfiniteQuery({
        queryKey: ['story-assets', storyId],
        queryFn: ({pageParam}) =>
            searchAssets({url: pageParam, story: storyId!, limit: 30}),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        enabled: !!storyId,
    });
    const pages = query.data?.pages;
    const items = useMemo(() => pages?.flatMap(p => p.items) ?? [], [pages]);

    return {...query, items, total: pages?.[0]?.total ?? 0};
}

/**
 * Horizontal strip of the assets contained in a story.
 *
 * Selecting one switches the asset displayed by the viewer in place
 * (`onSelect`) instead of navigating: the story context — this strip and the
 * previous / next navigation inside the story — is kept.
 */
export function StoryCarousel({
    storyId,
    currentAssetId,
    onSelect,
}: {
    storyId: string;
    /** The asset currently displayed, the story itself or one of its items */
    currentAssetId: string;
    onSelect: (assetId: string) => void;
}) {
    const {t} = useTranslation();
    const query = useStoryAssets(storyId);
    const {items, total} = query;

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
            {items.map(child => (
                <button
                    key={child.id}
                    type="button"
                    aria-current={child.id === currentAssetId}
                    className={cn(
                        'size-20 shrink-0 overflow-hidden rounded-md border bg-media-bg hover:ring-2 hover:ring-primary',
                        child.id === currentAssetId && 'ring-2 ring-primary'
                    )}
                    onClick={() => onSelect(child.id)}
                    title={child.name}
                >
                    <AssetThumb asset={child} size={80} />
                </button>
            ))}
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
