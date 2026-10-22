'use client';

import {useMemo} from 'react';
import {useInfiniteQuery} from '@tanstack/react-query';
import type {
    Asset,
    AttributeDefinition,
    AttributeEntity,
    EntityList,
} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {searchAssets} from '@/lib/api/assets';
import {getAttributesOfDefinition} from '@/lib/api/metadata';
import {useNarrowDefinitions} from '@/features/attributes/useAttributeDefinitionList';
import {assetIdOf} from '@/features/faces/useFaceRecognition';
import {
    entityAssetsCondition,
    entityAssetsSearchUrl,
    entityListDefinitions,
    entityListSlugs,
} from './entityAssetsSearch';

/**
 * The attribute definitions of the workspace taking their values from the
 * list, all of them (they all make the search of the values); `loaded` once
 * done (or failed).
 */
export function useEntityListDefinitions(
    workspaceId: string | undefined,
    listId: string
): {definitions: AttributeDefinition[]; loaded: boolean} {
    const {definitions, loaded} = useNarrowDefinitions(
        {workspaceId, entityListId: listId},
        {enabled: !!workspaceId}
    );

    return useMemo(
        () => ({
            definitions: workspaceId
                ? entityListDefinitions(definitions, workspaceId, listId)
                : [],
            loaded,
        }),
        [definitions, workspaceId, listId, loaded]
    );
}

/**
 * The assets holding a value of the list, and the search listing them. The
 * values not approved yet are left out of the search index: their assets are
 * found through the attributes holding them (no search then).
 *
 */
export function useEntityAssets({
    entity,
    list,
    workspaceId,
    pageSize,
    enabled = true,
    staleTime,
}: {
    entity: AttributeEntity;
    list: Pick<EntityList, 'id'>;
    workspaceId: string;
    pageSize: number;
    enabled?: boolean;
    staleTime?: number;
}) {
    const {definitions: listDefinitions} = useEntityListDefinitions(
        workspaceId,
        list.id
    );
    const slugs = useMemo(
        () => entityListSlugs(listDefinitions),
        [listDefinitions]
    );
    const definitionIds = useMemo(
        () => listDefinitions.map(d => d.id),
        [listDefinitions]
    );
    const indexed = entity.status === AttributeEntityStatus.Approved;
    const condition = entityAssetsCondition(slugs, entity.id);
    const url = indexed
        ? entityAssetsSearchUrl(slugs, entity.id, workspaceId)
        : undefined;

    const query = useInfiniteQuery({
        queryKey: ['entity-assets', entity.id, indexed, slugs, pageSize],
        enabled: enabled && !!condition,
        staleTime,
        queryFn: async ({pageParam, signal}) => {
            if (indexed) {
                return searchAssets(
                    {
                        url: pageParam,
                        conditions: condition ? [condition.query] : [],
                        workspaces: [workspaceId],
                        limit: pageSize,
                    },
                    signal
                );
            }

            const attributes = await getAttributesOfDefinition(
                {
                    url: pageParam,
                    definition: definitionIds,
                    value: entity.id,
                    limit: pageSize,
                },
                signal
            );
            const ids = [
                ...new Set(
                    attributes.items
                        .map(assetIdOf)
                        .filter((id): id is string => !!id)
                ),
            ];
            const found =
                ids.length > 0
                    ? (await searchAssets({ids, limit: ids.length}, signal))
                          .items
                    : [];
            const byId = new Map(found.map(a => [a.id, a]));

            return {
                items: ids
                    .map(id => byId.get(id))
                    .filter((a): a is Asset => !!a),
                next: attributes.next,
                total: attributes.total,
            };
        },
        initialPageParam: undefined as string | undefined,
        getNextPageParam: last => last.next,
    });
    const items = useMemo(
        () => uniqueById(query.data?.pages.flatMap(p => p.items) ?? []),
        [query.data]
    );

    return {
        items,
        total: query.data?.pages[0]?.total as number | undefined,
        url,
        condition,
        query,
    };
}

function uniqueById<T extends {id: string}>(items: T[]): T[] {
    const seen = new Set<string>();

    return items.filter(i => !seen.has(i.id) && !!seen.add(i.id));
}
