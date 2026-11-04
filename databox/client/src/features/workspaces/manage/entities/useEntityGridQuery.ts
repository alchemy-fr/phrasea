'use client';

import {useCallback} from 'react';
import {
    type InfiniteData,
    keepPreviousData,
    useInfiniteQuery,
    useQuery,
    useQueryClient,
} from '@tanstack/react-query';
import type {AttributeEntity, Page} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {
    type AttributeEntitySortField,
    getAttributeEntities,
} from '@/lib/api/metadata';

export type EntityStatusFilter = 'all' | 'pending' | 'approved' | 'rejected';

export type EntityGridFilters = {
    /** Global search on the value; takes precedence over the `value` column filter */
    search: string;
    status: EntityStatusFilter;
    value?: string;
    note?: string;
};

export type EntityGridSort = {id: AttributeEntitySortField; desc: boolean};

export const ENTITY_GRID_PAGE_SIZE = 100;

type Pages = InfiniteData<Page<AttributeEntity>, string | undefined>;

export function entityGridQueryKey(
    listId: string,
    sort?: EntityGridSort,
    filters?: EntityGridFilters
) {
    return sort && filters
        ? ['attribute-entities', 'grid', listId, {sort, filters}]
        : ['attribute-entities', 'grid', listId];
}

export function pendingCountQueryKey(listId: string) {
    return ['attribute-entities', 'pending-count', listId];
}

function statusParam(
    status: EntityStatusFilter
): AttributeEntityStatus[] | undefined {
    switch (status) {
        case 'pending':
            return [AttributeEntityStatus.Pending];
        case 'approved':
            return [AttributeEntityStatus.Approved];
        case 'rejected':
            return [AttributeEntityStatus.Rejected];
        default:
            return undefined;
    }
}

function mapPages(
    data: Pages | undefined,
    fn: (page: Page<AttributeEntity>, index: number) => Page<AttributeEntity>
): Pages | undefined {
    return data ? {...data, pages: data.pages.map(fn)} : data;
}

/** The pages with the entity patched in place (it keeps its position) */
export function applyPatchToPages(
    data: Pages | undefined,
    id: string,
    patch: Partial<AttributeEntity>
): Pages | undefined {
    return mapPages(data, page => ({
        ...page,
        items: page.items.map(e => (e.id === id ? {...e, ...patch} : e)),
    }));
}

export function insertIntoPages(
    data: Pages | undefined,
    entity: AttributeEntity
): Pages | undefined {
    return mapPages(data, (page, i) =>
        i === 0
            ? {
                  ...page,
                  total: page.total + 1,
                  items: [
                      entity,
                      ...page.items.filter(e => e.id !== entity.id),
                  ],
              }
            : {...page, total: page.total + 1}
    );
}

export function removeFromPages(
    data: Pages | undefined,
    ids: string[]
): Pages | undefined {
    const removed = new Set(ids);
    const count = data
        ? data.pages.reduce(
              (n, p) => n + p.items.filter(e => removed.has(e.id)).length,
              0
          )
        : 0;

    return mapPages(data, page => ({
        ...page,
        total: Math.max(0, page.total - count),
        items: page.items.filter(e => !removed.has(e.id)),
    }));
}

export function useEntityGridQuery({
    listId,
    sort,
    filters,
}: {
    listId: string;
    sort: EntityGridSort;
    filters: EntityGridFilters;
}) {
    const queryClient = useQueryClient();
    const queryKey = entityGridQueryKey(listId, sort, filters);
    const query = useInfiniteQuery({
        queryKey,
        queryFn: ({pageParam}) =>
            getAttributeEntities({
                list: listId,
                value: filters.search || filters.value || undefined,
                note: filters.note || undefined,
                status: statusParam(filters.status),
                usage: true,
                limit: ENTITY_GRID_PAGE_SIZE,
                order: {[sort.id]: sort.desc ? 'desc' : 'asc'},
                url: pageParam,
            }),
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
        placeholderData: keepPreviousData,
    });

    const keyHash = JSON.stringify(queryKey);
    const update = useCallback(
        (fn: (data: Pages | undefined) => Pages | undefined) =>
            queryClient.setQueryData<Pages>(JSON.parse(keyHash), fn),
        [queryClient, keyHash]
    );

    const patchEntity = useCallback(
        (id: string, patch: Partial<AttributeEntity>) =>
            update(data => applyPatchToPages(data, id, patch)),
        [update]
    );
    /** The PATCH response does not count the usage: the listed one is kept */
    const replaceEntity = useCallback(
        (saved: AttributeEntity) =>
            update(data =>
                mapPages(data, page => ({
                    ...page,
                    items: page.items.map(e =>
                        e.id === saved.id
                            ? {...saved, usage: saved.usage ?? e.usage}
                            : e
                    ),
                }))
            ),
        [update]
    );
    const insertEntity = useCallback(
        (entity: AttributeEntity) =>
            update(data => insertIntoPages(data, entity)),
        [update]
    );
    const removeEntities = useCallback(
        (ids: string[]) => update(data => removeFromPages(data, ids)),
        [update]
    );

    const pages = query.data?.pages;

    return {
        items: pages?.flatMap(p => p.items) ?? [],
        total: pages?.[0]?.total,
        isLoading: query.isLoading,
        isFetchingNextPage: query.isFetchingNextPage,
        hasNextPage: query.hasNextPage,
        fetchNextPage: query.fetchNextPage,
        refetch: query.refetch,
        patchEntity,
        replaceEntity,
        insertEntity,
        removeEntities,
    };
}

export function usePendingCount(listId: string) {
    return useQuery({
        queryKey: pendingCountQueryKey(listId),
        queryFn: () =>
            getAttributeEntities({
                list: listId,
                status: AttributeEntityStatus.Pending,
                limit: 1,
            }).then(p => p.total),
    });
}
