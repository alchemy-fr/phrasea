'use client';

import {useMemo} from 'react';
import {useInfiniteQuery} from '@tanstack/react-query';
import {searchAssets} from '@/lib/api/assets';
import {AssetList} from '@/features/assets/list/AssetList';
import {SelectionProvider} from '@/features/assets/list/SelectionProvider';
import {useAssetOpener} from '@/features/assets/useAssetOpener';
import {Skeleton} from '@/components/ui/misc';

type Attrs = {
    savedSearchId?: string | null;
    maxItems?: number;
    thumbSize?: number;
    height?: number;
    openAsset?: boolean;
};

/**
 * Grid of the results of a saved search, embedded in a CMS page.
 */
export function SearchGridWidget({attrs}: {attrs: Attrs}) {
    const openAsset = useAssetOpener();
    const limit = Math.min(attrs.maxItems ?? 20, 100);
    const query = useInfiniteQuery({
        queryKey: ['cms-search-grid', attrs.savedSearchId, limit],
        queryFn: ({pageParam}) =>
            searchAssets({
                url: pageParam,
                savedSearch: attrs.savedSearchId ?? undefined,
                limit,
            }),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        enabled: !!attrs.savedSearchId,
    });
    const pages = useMemo(
        () => (query.data?.pages ?? []).map(p => p.items),
        [query.data]
    );

    if (!attrs.savedSearchId) {
        return (
            <div className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                Search grid: select a saved search
            </div>
        );
    }
    if (query.isLoading) {
        return (
            <Skeleton
                className="w-full"
                style={{height: attrs.height ?? 600}}
            />
        );
    }

    return (
        <div
            className="rounded-lg border"
            style={{height: attrs.height ?? 600}}
        >
            <SelectionProvider>
                <AssetList
                    pages={pages}
                    loading={false}
                    loadingMore={query.isFetchingNextPage}
                    hasMore={query.hasNextPage}
                    onLoadMore={() =>
                        query.fetchNextPage().then(() => undefined)
                    }
                    layout="grid"
                    thumbSize={attrs.thumbSize ?? 200}
                    onOpen={attrs.openAsset === false ? undefined : openAsset}
                    noPreview
                />
            </SelectionProvider>
        </div>
    );
}
