import React, {useCallback, useEffect, useState} from 'react';
import {
    createDefaultPagination,
    createPaginatedLoader,
    Pagination,
} from '../api/pagination.ts';
import {Entity} from '../types.ts';
import useEffectOnce from '@alchemy/react-hooks/src/useEffectOnce';
import {NormalizedCollectionResponse} from '@alchemy/api';

type Props<T extends Entity, I extends Entity = T> = {
    items: I[];
    loadItems: (options?: Record<string, any>) => Promise<void>;
    hasMore?: boolean;
    loadMore?: () => Promise<void>;
    search: (
        query?: string,
        next?: string,
        options?: Record<string, any>
    ) => Promise<NormalizedCollectionResponse<T>>;
};

export function useSearch<T extends Entity, I extends Entity = T>({
    items,
    hasMore,
    loadMore,
    loadItems,
    search,
}: Props<T, I>) {
    const [searchQuery, setSearchQuery] = React.useState<string>('');
    const [searchQueryOptions, setSearchQueryOptions] = React.useState<
        Record<string, any>
    >({});
    const [searchResult, setSearchResult] = React.useState<Pagination<T>>({
        ...createDefaultPagination<T>(),
        loading: false,
    });
    const [loadedSearchQuery, setLoadedSearchQuery] = useState<
        string | undefined
    >();

    useEffectOnce(() => {
        loadItems(searchQueryOptions);
    }, []);

    const searchHandler = useCallback(
        // eslint-disable-next-line react-hooks/use-memo
        createPaginatedLoader<T>(async next => {
            const r = await search(searchQuery, next, searchQueryOptions);
            setLoadedSearchQuery(searchQuery);

            return r;
        }, setSearchResult),

        [searchQuery, searchQueryOptions]
    );

    useEffect(() => {
        if (!searchQuery) {
            setLoadedSearchQuery(undefined);
        }
    }, [searchQuery]);

    const loadMoreHandler = () =>
        loadedSearchQuery
            ? searchHandler(searchResult.next || undefined)
            : loadMore?.();
    const hasLoadMore = loadedSearchQuery ? !!searchResult.next : hasMore;
    const results = loadedSearchQuery ? searchResult?.pages.flat() : items;

    return {
        searchQuery,
        setSearchQuery,
        searchQueryOptions,
        setSearchQueryOptions,
        searchResult,
        results,
        loading: searchResult.loading,
        loadMoreHandler,
        hasMore: hasLoadMore,
        searchHandler,
        isSearch: Boolean(loadedSearchQuery),
    };
}
