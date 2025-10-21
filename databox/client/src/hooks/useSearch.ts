import React from 'react';
import {
    createDefaultPagination,
    createPaginatedLoader,
    Pagination,
} from '../api/pagination.ts';
import {Entity} from '../types.ts';
import useEffectOnce from '@alchemy/react-hooks/src/useEffectOnce';
import {ApiCollectionResponse} from '../api/hydra.ts';

type Props<T extends Entity, I extends Entity = T> = {
    items: I[];
    loadItems: () => Promise<void>;
    hasMore?: boolean;
    loadMore?: () => Promise<void>;
    search: (
        query?: string,
        next?: string
    ) => Promise<ApiCollectionResponse<T>>;
};

export function useSearch<T extends Entity, I extends Entity = T>({
    items,
    hasMore,
    loadMore,
    loadItems,
    search,
}: Props<T, I>) {
    const [searchQuery, setSearchQuery] = React.useState<string>('');
    const [searchResult, setSearchResult] = React.useState<Pagination<T>>({
        ...createDefaultPagination<T>(),
        loading: false,
    });
    const [loadedSearchQuery, setLoadedSearchQuery] = React.useState<
        string | undefined
    >();

    useEffectOnce(() => {
        loadItems();
    }, []);

    const searchHandler = React.useCallback(
        createPaginatedLoader<T>(async next => {
            const r = await search(searchQuery, next);
            setLoadedSearchQuery(searchQuery);

            return r;
        }, setSearchResult),
        [searchQuery]
    );

    React.useEffect(() => {
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
        searchResult,
        results,
        loading: searchResult.loading,
        loadMoreHandler,
        hasMore: hasLoadMore,
        searchHandler,
        isSearch: Boolean(loadedSearchQuery),
    };
}
