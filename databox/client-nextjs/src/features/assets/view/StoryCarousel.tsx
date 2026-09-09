'use client';

import {useTranslation} from 'react-i18next';
import {useInfiniteQuery} from '@tanstack/react-query';
import type {Asset} from '@/types/api';
import {searchAssets} from '@/lib/api/assets';
import {AssetThumb} from '@/features/assets/list/AssetThumb';
import {useAssetOpener} from '@/features/assets/useAssetOpener';
import {Button} from '@/components/ui/button';
import {Skeleton} from '@/components/ui/misc';

/**
 * Horizontal strip of the assets contained in a story.
 */
export function StoryCarousel({asset}: {asset: Asset}) {
    const {t} = useTranslation();
    const openAsset = useAssetOpener();
    const query = useInfiniteQuery({
        queryKey: ['story-assets', asset.id],
        queryFn: ({pageParam}) =>
            searchAssets({url: pageParam, story: asset.id, limit: 30}),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
    });
    const items = query.data?.pages.flatMap(p => p.items) ?? [];
    const total = query.data?.pages[0]?.total ?? 0;

    return (
        <div className="flex h-28 shrink-0 items-center gap-2 overflow-x-auto border-t bg-background px-3 py-2">
            <span className="mr-1 text-xs font-medium text-muted-foreground">
                {t('asset.story.items', '{{count}} items', {count: total})}
            </span>
            {query.isLoading
                ? [...Array(6)].map((_, i) => (
                      <Skeleton key={i} className="size-20 shrink-0" />
                  ))
                : null}
            {items.map(child => (
                <button
                    key={child.id}
                    type="button"
                    className="size-20 shrink-0 overflow-hidden rounded-md border bg-media-bg hover:ring-2 hover:ring-primary"
                    onClick={() =>
                        openAsset(child, {siblings: items.map(i => i.id)})
                    }
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
