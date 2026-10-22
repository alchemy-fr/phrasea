import {useCallback, useMemo, useRef, useState} from 'react';
import {useInfiniteQuery} from '@tanstack/react-query';
import type {Page} from '@/types/api';
import {useDebouncedValue} from './useDebouncedValue';

export type InfiniteListFetch<T> = (args: {
    /** The trimmed, debounced search ('' without one) */
    query: string;
    /** `next` of the previous page; the getter then ignores its params */
    url?: string;
    signal: AbortSignal;
}) => Promise<Page<T>>;

export type InfiniteList<T> = {
    /** The loaded pages, an item listed once */
    items: T[];
    total?: number;
    query: string;
    setQuery: (query: string) => void;
    /** The typed search is not applied to `items` yet */
    searching: boolean;
    hasMore: boolean;
    loadMore: () => void;
    /** First load (no item to show yet) */
    loading: boolean;
    loadingMore: boolean;
    /** Error of the last page load */
    error: unknown;
    /** Every item is loaded: no search and no next page */
    complete: boolean;
    /** Loads the remaining pages: an explicit action only, never by default */
    loadAll: () => Promise<void>;
    refetch: () => Promise<unknown>;
};

/** Guard of `loadAll` */
const MAX_PAGES = 100;

/**
 * A paginated API list loaded page by page (`loadMore`, for an infinite
 * scroll), searched server side: the search is part of the query key, so
 * that each search has its own pages. The previous items stay shown while
 * the next search loads.
 *
 * The end of the list is the absence of `next`, never `items.length` reaching
 * `total`: a page may be shorter than the limit.
 */
export function useInfiniteList<T extends {id: string}>({
    queryKey,
    fetchPage,
    enabled = true,
    debounce = 250,
    staleTime,
}: {
    queryKey: unknown[];
    fetchPage: InfiniteListFetch<T>;
    enabled?: boolean;
    debounce?: number;
    staleTime?: number;
}): InfiniteList<T> {
    const [query, setQuery] = useState('');
    const debounced = useDebouncedValue(query.trim(), debounce);
    const fetchRef = useRef(fetchPage);
    fetchRef.current = fetchPage;
    const baseKey = JSON.stringify(queryKey);

    const result = useInfiniteQuery({
        queryKey: [...queryKey, {query: debounced}],
        queryFn: ({pageParam, signal}) =>
            fetchRef.current({query: debounced, url: pageParam, signal}),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        // Only across searches: the items of another list must not show up
        placeholderData: (previous, previousQuery) =>
            previousQuery &&
            JSON.stringify(previousQuery.queryKey.slice(0, -1)) === baseKey
                ? previous
                : undefined,
        enabled,
        staleTime,
    });
    const {
        data,
        hasNextPage,
        isFetchingNextPage,
        fetchNextPage,
        isPlaceholderData,
        refetch,
    } = result;

    const items = useMemo(() => {
        const seen = new Set<string>();
        const list: T[] = [];
        // The offsets move when the list changes between two pages
        data?.pages.forEach(p =>
            p.items.forEach(item => {
                if (!seen.has(item.id)) {
                    seen.add(item.id);
                    list.push(item);
                }
            })
        );

        return list;
    }, [data]);

    const loadMore = useCallback(() => {
        if (hasNextPage && !isFetchingNextPage) {
            void fetchNextPage();
        }
    }, [hasNextPage, isFetchingNextPage, fetchNextPage]);

    const loadAll = useCallback(async () => {
        let current: {hasNextPage: boolean; isError: boolean} = result;
        for (let i = 0; i < MAX_PAGES && current.hasNextPage; i++) {
            current = await fetchNextPage();
            if (current.isError) {
                break;
            }
        }
    }, [result, fetchNextPage]);

    return {
        items,
        total: data?.pages[0]?.total,
        query,
        setQuery,
        searching:
            query.trim() !== debounced ||
            (isPlaceholderData && result.isFetching),
        hasMore: !!hasNextPage,
        loadMore,
        loading: result.isLoading,
        loadingMore: isFetchingNextPage,
        error: result.error,
        complete:
            !!data && !hasNextPage && debounced === '' && !isPlaceholderData,
        loadAll,
        refetch,
    };
}
