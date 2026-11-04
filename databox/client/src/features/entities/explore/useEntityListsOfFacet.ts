import {useMemo} from 'react';
import {AttributeType, type AttributeDefinition} from '@/types/api';
import {
    slugOfSearchSlug,
    useDefinitionsWithSlug,
    workspaceIdOf,
} from '@/features/attributes/definitionsStore';
import {idFromIri} from '@/lib/utils/iri';

export type EntityListRef = {listId: string; workspaceId: string};

/**
 * The entity lists behind a facet: the facet is named after the search slug
 * of its attribute (with a locale suffix when translatable, as resolved by
 * `useFacetField`), which several workspaces may share with lists of their own.
 */
export function entityListsOfFacet(
    definitions: AttributeDefinition[],
    name: string
): EntityListRef[] {
    const enabled = definitions.filter(d => d.enabled);
    let matching = enabled.filter(d => d.searchSlug === name);
    if (matching.length === 0) {
        const unlocalized = name.replace(/_[a-z]{2}$/, '');
        matching = enabled.filter(d => d.searchSlug === unlocalized);
    }

    const lists = new Map<string, EntityListRef>();
    matching.forEach(d => {
        const list = d.entityList;
        const listId = typeof list === 'string' ? idFromIri(list) : list?.id;
        const workspaceId = workspaceIdOf(d);
        if (
            d.type === AttributeType.Entity &&
            listId &&
            workspaceId &&
            !lists.has(listId)
        ) {
            lists.set(listId, {listId, workspaceId});
        }
    });

    return [...lists.values()];
}

export function useEntityListsOfFacet(name: string): EntityListRef[] {
    // Every workspace sharing the slug shares the facet
    const definitions = useDefinitionsWithSlug(slugOfSearchSlug(name));

    return useMemo(
        () => entityListsOfFacet(definitions, name),
        [definitions, name]
    );
}
