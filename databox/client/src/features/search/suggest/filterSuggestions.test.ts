import {describe, expect, it} from 'vitest';
import type {TFunction} from 'i18next';
import {
    AttributeDefinition,
    AttributeDefinitionOrBuiltIn,
    AttributeType,
    BuiltInAttribute,
    Facets,
    FacetType,
} from '@/types/api';
import {
    apiItemToValueSuggestion,
    buildFilterCondition,
    clientValueSuggestions,
    findConditionId,
    isApiSuggestable,
    isFieldToken,
    mergeValueSuggestions,
    parseSearchInput,
    rawSuggestionFor,
    suggestFields,
} from './filterSuggestions';

const t = ((key: string, fallback?: string) => fallback ?? key) as TFunction;

function builtIn(
    id: string,
    displayName: string,
    type: AttributeType
): BuiltInAttribute {
    return {
        id,
        name: id,
        slug: id,
        searchSlug: id,
        displayName,
        type,
        builtIn: true,
        enabled: true,
        searchable: true,
        sortable: true,
        multiple: false,
        facetEnabled: true,
    } as BuiltInAttribute;
}

function attribute(
    slug: string,
    displayName: string,
    type: AttributeType,
    extra: Partial<AttributeDefinition> = {}
): AttributeDefinition {
    return {
        id: `def-${slug}`,
        name: displayName,
        slug,
        searchSlug: `${slug}_${type}_s`,
        displayName,
        type,
        enabled: true,
        searchable: true,
        sortable: true,
        multiple: false,
        facetEnabled: true,
        ...extra,
    } as AttributeDefinition;
}

const story = builtIn('@story', 'Stories', AttributeType.Story);
const isStory = builtIn('@isStory', 'Is story', AttributeType.Boolean);
const privacy = builtIn('@privacy', 'Privacy', AttributeType.Privacy);
const size = builtIn('@size', 'File size', AttributeType.FileSize);
const tag = builtIn('@tag', 'Tags', AttributeType.Tag);
const title = attribute('title', 'Title', AttributeType.Text);
const history = attribute('history', 'History', AttributeType.Text);
const hidden = attribute('secret', 'Story secret', AttributeType.Text, {
    searchable: false,
});
const place = attribute('place', 'Place', AttributeType.Entity);
const shot = attribute('shot', 'Shot date', AttributeType.Date);
const notes = attribute('notes', 'Story notes', AttributeType.Textarea);
const body = attribute('body', 'History body', AttributeType.Html);

const definitions: AttributeDefinitionOrBuiltIn[] = [
    title,
    history,
    hidden,
    story,
    isStory,
    privacy,
    size,
    tag,
    place,
    shot,
    notes,
    body,
];

describe('parseSearchInput', () => {
    it('detects a field being filtered', () => {
        expect(parseSearchInput('@isStory:')).toEqual({
            mode: 'field',
            field: '@isStory',
            valuePrefix: '',
        });
        expect(parseSearchInput('title: chat ')).toEqual({
            mode: 'field',
            field: 'title',
            valuePrefix: 'chat',
        });
    });

    it('keeps everything else as text', () => {
        expect(parseSearchInput('story')).toEqual({
            mode: 'text',
            text: 'story',
        });
        expect(parseSearchInput('foo bar:baz')).toEqual({
            mode: 'text',
            text: 'foo bar:baz',
        });
    });
});

describe('isFieldToken', () => {
    it('accepts a single word or a bare @', () => {
        expect(isFieldToken('story')).toBe(true);
        expect(isFieldToken('@is')).toBe(true);
        expect(isFieldToken('@')).toBe(true);
        expect(isFieldToken('s')).toBe(false);
        expect(isFieldToken('two words')).toBe(false);
        expect(isFieldToken('"quoted"')).toBe(false);
    });
});

describe('suggestFields', () => {
    it('matches keys and names, exact key first, built-ins first', () => {
        const items = suggestFields('story', definitions);
        // "history" contains "story" too, after the built-ins
        expect(items.map(i => i.key)).toEqual([
            '@story',
            '@isStory',
            'history',
        ]);
        expect(items[0].text).toBe('@story:');
        expect(items[0].hl).toBe('@[hl]story[/hl]:');
        expect(items[1].hl).toBe('@is[hl]Story[/hl]:');
    });

    it('excludes long text fields', () => {
        expect(suggestFields('notes', definitions)).toEqual([]);
        expect(suggestFields('body', definitions)).toEqual([]);
        expect(isApiSuggestable(notes)).toBe(false);
        expect(isApiSuggestable(title)).toBe(true);
    });

    it('excludes non-searchable attributes and matches names', () => {
        expect(suggestFields('hist', definitions).map(i => i.key)).toEqual([
            'history',
        ]);
        expect(suggestFields('secret', definitions)).toEqual([]);
        expect(suggestFields('file', definitions).map(i => i.key)).toEqual([
            '@size',
        ]);
    });

    it('lists built-ins only after @', () => {
        expect(suggestFields('@', definitions).map(i => i.key)).toEqual([
            '@size',
            '@isStory',
            '@privacy',
            '@story',
            '@tag',
        ]);
        expect(suggestFields('@t', definitions).map(i => i.key)).toEqual([
            '@tag',
        ]);
    });

    it('caps the list', () => {
        const many = Array.from({length: 12}, (_, i) =>
            attribute(`field${i}`, `Field ${i}`, AttributeType.Text)
        );
        expect(suggestFields('field', many)).toHaveLength(8);
    });
});

describe('clientValueSuggestions', () => {
    it('suggests booleans by label or literal', () => {
        const all = clientValueSuggestions({
            definition: isStory,
            prefix: '',
            t,
        });
        expect(all.map(s => [s.value, s.label, s.query])).toEqual([
            [true, 'Yes', '@isStory IS true'],
            [false, 'No', '@isStory IS false'],
        ]);
        expect(
            clientValueSuggestions({definition: isStory, prefix: 'tr', t}).map(
                s => s.value
            )
        ).toEqual([true]);
        expect(
            clientValueSuggestions({definition: isStory, prefix: 'n', t}).map(
                s => s.value
            )
        ).toEqual([false]);
    });

    it('suggests the workspaces from the store', () => {
        const workspace = builtIn(
            '@workspace',
            'Workspace',
            AttributeType.Workspace
        );
        const workspaces = [
            {id: 'w1', name: 'Newspaper', displayName: 'Newspaper'},
            {id: 'w2', name: 'Photos'},
        ] as any;
        expect(
            clientValueSuggestions({
                definition: workspace,
                prefix: 'ph',
                workspaces,
                t,
            }).map(s => [s.value, s.label, s.query])
        ).toEqual([['w2', 'Photos', '@workspace IS "w2"']]);
        expect(
            clientValueSuggestions({
                definition: workspace,
                prefix: '',
                workspaces,
                t,
            })
        ).toHaveLength(2);
    });

    it('suggests the privacy levels with their numeric value', () => {
        const items = clientValueSuggestions({
            definition: privacy,
            prefix: 'public',
            t,
        });
        expect(items.length).toBeGreaterThan(0);
        expect(items[0].query).toMatch(/^@privacy IS \d$/);
        expect(items.every(s => typeof s.value === 'number')).toBe(true);
    });

    it('suggests the facet buckets, entities by id', () => {
        const facets: Facets = {
            '@tag': {
                meta: {
                    displayName: 'Tags',
                    sortable: false,
                    widget: FacetType.Entity,
                },
                buckets: [
                    {key: {value: 't1', label: 'Nature'}, doc_count: 3},
                    {key: {value: 't2', label: 'Urban'}, doc_count: 1},
                ],
            },
            'title_text_s_fr': {
                meta: {
                    displayName: 'Title',
                    sortable: false,
                    widget: FacetType.Text,
                },
                buckets: [{key: 'Chat', doc_count: 1}],
            },
        };
        // Prefix matches first, then substrings
        expect(
            clientValueSuggestions({
                definition: tag,
                prefix: 'ur',
                facets,
                t,
            }).map(s => [s.value, s.label, s.query])
        ).toEqual([
            ['t2', 'Urban', '@tag IS "t2"'],
            ['t1', 'Nature', '@tag IS "t1"'],
        ]);
        expect(
            clientValueSuggestions({
                definition: title,
                prefix: '',
                facets,
                t,
            }).map(s => s.query)
        ).toEqual(['title IS "Chat"']);
        expect(
            clientValueSuggestions({definition: tag, prefix: 'zzz', facets, t})
        ).toEqual([]);
    });
});

describe('API values', () => {
    it('maps entity items to their id and drops the ones without', () => {
        expect(
            apiItemToValueSuggestion(
                {
                    id: '1',
                    name: 'Paris',
                    hl: '[hl]Par[/hl]is',
                    t: place.id,
                    tName: 'Place',
                    entityId: 'e1',
                },
                place
            )
        ).toMatchObject({value: 'e1', label: 'Paris', query: 'place IS "e1"'});
        expect(
            apiItemToValueSuggestion(
                {
                    id: '1',
                    name: 'Paris',
                    hl: 'Paris',
                    t: place.id,
                    tName: 'Place',
                },
                place
            )
        ).toBeNull();
        expect(
            apiItemToValueSuggestion(
                {
                    id: '1',
                    name: 'Chat',
                    hl: 'Chat',
                    t: title.id,
                    tName: 'Title',
                },
                title
            )
        ).toMatchObject({value: 'Chat', query: 'title IS "Chat"'});
    });

    it('merges without duplicates', () => {
        const client = clientValueSuggestions({
            definition: title,
            prefix: '',
            facets: {
                title_text_s: {
                    meta: {displayName: 'Title', sortable: false},
                    buckets: [{key: 'Chat', doc_count: 1}],
                },
            },
            t,
        });
        const api = ['Chat', 'Chien'].map(
            name =>
                apiItemToValueSuggestion(
                    {id: name, name, hl: name, t: title.id, tName: 'Title'},
                    title
                )!
        );
        expect(mergeValueSuggestions(client, api).map(s => s.value)).toEqual([
            'Chat',
            'Chien',
        ]);
        expect(mergeValueSuggestions(client, api, 1)).toHaveLength(1);
    });
});

describe('rawSuggestionFor', () => {
    it('offers the typed text for text, number and date fields', () => {
        expect(rawSuggestionFor(title, 'chat', [])).toMatchObject({
            kind: 'raw',
            query: 'title IS "chat"',
        });
        expect(rawSuggestionFor(size, '100', [])).toMatchObject({
            query: '@size IS 100',
        });
        expect(rawSuggestionFor(shot, '2024-01-01', [])).toMatchObject({
            query: 'shot IS "2024-01-01"',
        });
    });

    it('refuses empty, unfit or already suggested values', () => {
        expect(rawSuggestionFor(title, '  ', [])).toBeNull();
        expect(rawSuggestionFor(isStory, 'true', [])).toBeNull();
        expect(rawSuggestionFor(tag, 'nature', [])).toBeNull();
        expect(rawSuggestionFor(size, 'abc', [])).toBeNull();
        expect(rawSuggestionFor(shot, 'yesterday?', [])).toBeNull();
        const values = clientValueSuggestions({
            definition: title,
            prefix: '',
            facets: {
                title_text_s: {
                    meta: {displayName: 'Title', sortable: false},
                    buckets: [{key: 'Chat', doc_count: 1}],
                },
            },
            t,
        });
        expect(rawSuggestionFor(title, 'chat', values)).toBeNull();
        expect(rawSuggestionFor(title, 'cha', values)).not.toBeNull();
    });
});

describe('buildFilterCondition', () => {
    it('types the value after the field', () => {
        expect(buildFilterCondition(isStory, true)).toBe('@isStory IS true');
        expect(buildFilterCondition(isStory, 'false')).toBe(
            '@isStory IS false'
        );
        expect(buildFilterCondition(title, 'say "hi"')).toBe(
            'title IS "say \\"hi\\""'
        );
        expect(buildFilterCondition(size, '100')).toBe('@size IS 100');
        expect(buildFilterCondition(privacy, 3)).toBe('@privacy IS 3');
        expect(buildFilterCondition(size, 'abc')).toBeNull();
        expect(buildFilterCondition(size, '')).toBeNull();
        expect(buildFilterCondition(title, null)).toBeNull();
    });
});

describe('findConditionId', () => {
    it('uses the facet name, reusing a locale-suffixed one', () => {
        expect(findConditionId([], isStory)).toBe('@isStory');
        expect(findConditionId([], title)).toBe('title_text_s');
        expect(
            findConditionId(
                [
                    {id: '@isStory', query: '@isStory IS true'},
                    {id: 'title_text_s_fr', query: 'title IS "a"'},
                ],
                title
            )
        ).toBe('title_text_s_fr');
    });
});
