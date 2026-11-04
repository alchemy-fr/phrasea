import {describe, expect, it} from 'vitest';
import type {AttributeDefinition} from '@/types/api';
import {
    entityAssetsCondition,
    entityAssetsSearchUrl,
    entityListDefinitions,
    entityListSlugs,
} from './entityAssetsSearch';

const def = (extra: Partial<AttributeDefinition>) =>
    ({id: 'd', slug: 'tag', ...extra}) as AttributeDefinition;

describe('entityAssetsSearch', () => {
    it('keeps the definitions of the list in the workspace', () => {
        const defs = [
            def({
                id: 'a',
                slug: 'a',
                workspace: '/workspaces/w1',
                entityList: '/entity-lists/l1',
            }),
            def({
                id: 'b',
                slug: 'b',
                workspace: '/workspaces/w1',
                entityList: {id: 'l1'} as AttributeDefinition['entityList'],
            }),
            def({
                id: 'c',
                slug: 'c',
                workspace: '/workspaces/w1',
                entityList: '/entity-lists/l2',
            }),
            def({
                id: 'd',
                slug: 'd',
                workspace: '/workspaces/w2',
                entityList: '/entity-lists/l1',
            }),
            def({
                id: 'e',
                slug: 'e',
                workspace: '/workspaces/w1',
                entityList: null,
            }),
        ];

        expect(
            entityListSlugs(entityListDefinitions(defs, 'w1', 'l1'))
        ).toEqual(['a', 'b']);
    });

    it('builds the condition of one attribute', () => {
        expect(entityAssetsCondition(['season'], 'e1')).toEqual({
            id: 'entity-e1',
            query: 'season = "e1"',
        });
    });

    it('builds the condition of several attributes', () => {
        expect(entityAssetsCondition(['a', 'b'], 'e1')?.query).toBe(
            '(a = "e1" OR b = "e1")'
        );
    });

    it('escapes the quotes', () => {
        expect(entityAssetsCondition(['a'], 'x"y')?.query).toBe('a = "x\\"y"');
    });

    it('has no condition without attribute', () => {
        expect(entityAssetsCondition([], 'e1')).toBeUndefined();
        expect(entityAssetsSearchUrl([], 'e1', 'w1')).toBeUndefined();
    });

    it('builds the search URL in the workspace', () => {
        const url = entityAssetsSearchUrl(['season'], 'e1', 'w1')!;
        const [path, query] = url.split('?');
        const params = new URLSearchParams(query);

        expect(path).toBe('/assets');
        expect(params.getAll('f')).toEqual([
            '@workspace:@workspace = "w1"',
            'entity-e1:season = "e1"',
        ]);
    });
});
