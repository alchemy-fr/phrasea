import {describe, expect, it} from 'vitest';
import type {AttributeDefinition} from '@/types/api';
import {entityListsOfFacet} from './useEntityListsOfFacet';

function definition(
    id: string,
    options: Partial<Record<keyof AttributeDefinition, unknown>>
): AttributeDefinition {
    return {
        id,
        enabled: true,
        type: 'entity',
        slug: 'persons',
        searchSlug: 'persons_entity_m',
        workspace: '/workspaces/ws-1',
        entityList: '/entity-lists/list-1',
        ...options,
    } as unknown as AttributeDefinition;
}

describe('entityListsOfFacet', () => {
    it('finds the list of a facet named after the search slug', () => {
        expect(
            entityListsOfFacet([definition('d1', {})], 'persons_entity_m')
        ).toEqual([{listId: 'list-1', workspaceId: 'ws-1'}]);
    });

    it('resolves a facet suffixed by a locale', () => {
        expect(
            entityListsOfFacet(
                [
                    definition('d1', {
                        searchSlug: 'keywords_entity_m',
                        entityList: {id: 'list-k'},
                        workspace: {id: 'ws-1'},
                    }),
                ],
                'keywords_entity_m_fr'
            )
        ).toEqual([{listId: 'list-k', workspaceId: 'ws-1'}]);
    });

    it('returns a list per workspace sharing the slug, once each', () => {
        expect(
            entityListsOfFacet(
                [
                    definition('d1', {}),
                    definition('d2', {
                        workspace: '/workspaces/ws-2',
                        entityList: '/entity-lists/list-2',
                    }),
                    definition('d3', {}),
                ],
                'persons_entity_m'
            )
        ).toEqual([
            {listId: 'list-1', workspaceId: 'ws-1'},
            {listId: 'list-2', workspaceId: 'ws-2'},
        ]);
    });

    it('ignores the definitions that are not entities, disabled or listless', () => {
        expect(
            entityListsOfFacet(
                [
                    definition('d1', {type: 'keyword'}),
                    definition('d2', {enabled: false}),
                    definition('d3', {entityList: null}),
                    definition('d4', {searchSlug: 'other_entity_m'}),
                ],
                'persons_entity_m'
            )
        ).toEqual([]);
    });
});
