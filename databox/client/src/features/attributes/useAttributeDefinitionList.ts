'use client';

import {useEffect, useMemo} from 'react';
import {useQuery} from '@tanstack/react-query';
import type {AttributeDefinition} from '@/types/api';
import {
    type AttributeDefinitionListOptions,
    getAttributeDefinitionsPage,
} from '@/lib/api/metadata';
import {fetchAllPages} from '@/lib/api/hydra';
import {type InfiniteList, useInfiniteList} from '@/hooks/useInfiniteList';
import {normalizeDefinition, useDefinitionsStore} from './definitionsStore';

export type AttributeDefinitionFilters = Omit<
    AttributeDefinitionListOptions,
    'query' | 'limit' | 'url'
>;

/**
 * The attribute definitions matching the filters, page by page and searched
 * by the API (name or slug): the loaded ones feed the definitions store.
 */
export function useAttributeDefinitionList(
    filters: AttributeDefinitionFilters,
    {pageSize = 50, enabled = true}: {pageSize?: number; enabled?: boolean} = {}
): InfiniteList<AttributeDefinition> {
    const list = useInfiniteList<AttributeDefinition>({
        queryKey: ['attribute-definitions', 'list', filters, pageSize],
        fetchPage: ({query, url, signal}) =>
            getAttributeDefinitionsPage(
                {...filters, query: query || undefined, limit: pageSize, url},
                signal
            ),
        enabled,
    });
    const ingest = useDefinitionsStore(s => s.ingest);
    const items = useMemo(
        () => list.items.map(normalizeDefinition),
        [list.items]
    );
    useEffect(() => {
        ingest(items);
    }, [items, ingest]);

    return useMemo(() => ({...list, items}), [list, items]);
}

/**
 * Every definition matching the filters, only for filters that bound the
 * result (an entity list, the searchable geo points, some slugs): the caller
 * needs them all.
 */
export function useNarrowDefinitions(
    filters: AttributeDefinitionFilters,
    {enabled = true}: {enabled?: boolean} = {}
): {definitions: AttributeDefinition[]; loaded: boolean} {
    const ingest = useDefinitionsStore(s => s.ingest);
    const query = useQuery({
        queryKey: ['attribute-definitions', 'narrow', filters],
        queryFn: async ({signal}) =>
            (
                await fetchAllPages(
                    getAttributeDefinitionsPage(
                        {...filters, limit: 100},
                        signal
                    ),
                    url => getAttributeDefinitionsPage({url}, signal)
                )
            ).map(normalizeDefinition),
        enabled,
    });
    const {data} = query;
    useEffect(() => {
        if (data) {
            ingest(data);
        }
    }, [data, ingest]);

    return {
        definitions: data ?? EMPTY,
        /** Once loaded, or failed */
        loaded: query.isSuccess || query.isError,
    };
}

const EMPTY: AttributeDefinition[] = [];
