import {describe, expect, it, vi} from 'vitest';
import {
    AttributeDefinition,
    AttributeType,
    BuiltInAttribute,
} from '@/types/api';
import {fetchRemoteValues, hasRemoteValues} from './remoteValues';

const api = vi.hoisted(() => ({
    getSearchSuggestions: vi.fn(),
    getCollections: vi.fn(),
    getTags: vi.fn(),
    getAttributeEntities: vi.fn(),
}));
vi.mock('@/lib/api/assets', () => ({
    getSearchSuggestions: api.getSearchSuggestions,
}));
vi.mock('@/lib/api/collections', () => ({getCollections: api.getCollections}));
vi.mock('@/lib/api/metadata', () => ({
    getTags: api.getTags,
    getAttributeEntities: api.getAttributeEntities,
}));

const builtIn = (type: AttributeType) =>
    ({
        id: '@x',
        slug: '@x',
        searchSlug: '@x',
        builtIn: true,
        type,
    }) as BuiltInAttribute;
const attribute = (type: AttributeType, extra: object = {}) =>
    ({
        id: 'd1',
        slug: 'a',
        searchSlug: 'a_text_s',
        type,
        ...extra,
    }) as AttributeDefinition;

describe('hasRemoteValues', () => {
    it('knows which fields have values to fetch', () => {
        expect(hasRemoteValues(builtIn(AttributeType.CollectionPath))).toBe(
            true
        );
        expect(hasRemoteValues(builtIn(AttributeType.Tag))).toBe(true);
        expect(hasRemoteValues(builtIn(AttributeType.Boolean))).toBe(false);
        expect(hasRemoteValues(builtIn(AttributeType.Workspace))).toBe(false);
        expect(hasRemoteValues(attribute(AttributeType.Text))).toBe(true);
        expect(hasRemoteValues(attribute(AttributeType.Number))).toBe(false);
        expect(hasRemoteValues(attribute(AttributeType.Textarea))).toBe(false);
        expect(hasRemoteValues(attribute(AttributeType.Html))).toBe(false);
        expect(
            hasRemoteValues(
                attribute(AttributeType.Entity, {
                    entityList: '/entity-lists/l1',
                })
            )
        ).toBe(true);
        expect(hasRemoteValues(attribute(AttributeType.Entity))).toBe(false);
    });
});

describe('fetchRemoteValues', () => {
    it('lists collections by their path', async () => {
        api.getCollections.mockResolvedValue({
            items: [
                {
                    id: 'c1',
                    name: 'Sub',
                    displayName: 'Sub',
                    absoluteDisplayName: 'Root / Sub',
                },
                {id: 'c2', name: 'Other', displayName: 'Other'},
            ],
        });
        await expect(
            fetchRemoteValues({
                definition: builtIn(AttributeType.CollectionPath),
                definitionIds: [],
                prefix: 'su',
            })
        ).resolves.toEqual([
            {value: 'c1', label: 'Root / Sub'},
            {value: 'c2', label: 'Other'},
        ]);
        expect(api.getCollections).toHaveBeenCalledWith({
            query: 'su',
            limit: 10,
        });
    });

    it('lists the entities of the list of an entity attribute', async () => {
        api.getAttributeEntities.mockResolvedValue({
            items: [{id: 'e1', value: 'Bike'}],
        });
        await expect(
            fetchRemoteValues({
                definition: attribute(AttributeType.Entity, {
                    entityList: '/entity-lists/l1',
                }),
                definitionIds: ['d1'],
                prefix: '',
            })
        ).resolves.toEqual([{value: 'e1', label: 'Bike'}]);
        expect(api.getAttributeEntities).toHaveBeenCalledWith({
            list: 'l1',
            query: undefined,
        });
    });

    it('asks the API for the indexed values of a text attribute across workspaces', async () => {
        api.getSearchSuggestions.mockResolvedValue({
            items: [
                {
                    id: '1',
                    name: 'Paris',
                    hl: '[hl]Par[/hl]is',
                    t: 'd1',
                    tName: 'A',
                },
            ],
        });
        await expect(
            fetchRemoteValues({
                definition: attribute(AttributeType.Text),
                definitionIds: ['d1', 'd2'],
                prefix: 'par',
            })
        ).resolves.toEqual([
            {value: 'Paris', label: 'Paris', hl: '[hl]Par[/hl]is'},
        ]);
        expect(api.getSearchSuggestions).toHaveBeenCalledWith(
            'par',
            {definitions: ['d1', 'd2']},
            undefined
        );
    });
});
