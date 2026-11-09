import {describe, expect, it} from 'vitest';
import {
    definitionProblems,
    fieldFromAttribute,
    fieldFromBuiltin,
    initialValues,
    isFieldCustomized,
    localized,
    newCustomField,
    newLayout,
    normalizeValues,
    validateValues,
    widgetOf,
} from './definition';
import type {DataboxField, FormDefinition} from './types';

const messages = {
    required: 'required',
    number: 'number',
    minLength: (n: number) => `min length ${n}`,
    maxLength: (n: number) => `max length ${n}`,
    min: (n: number) => `min ${n}`,
    max: (n: number) => `max ${n}`,
};

const definition: FormDefinition = {
    version: 1,
    fields: [
        {id: 's', source: 'layout', kind: 'section', label: {_: 'Info'}},
        {
            id: 'a',
            source: 'custom',
            key: 'title',
            type: 'text',
            required: true,
            label: {},
            constraints: {maxLength: 5},
        },
        {
            id: 'b',
            source: 'custom',
            key: 'count',
            type: 'number',
            label: {},
            constraints: {min: 1, max: 3},
        },
        {
            id: 'c',
            source: 'custom',
            key: 'keywords',
            type: 'text',
            multiple: true,
            label: {},
        },
        {
            id: 'd',
            source: 'custom',
            key: 'agreed',
            type: 'boolean',
            required: true,
            label: {},
        },
        {
            id: 'e',
            source: 'custom',
            key: 'origin',
            type: 'text',
            hidden: true,
            defaultValue: 'web',
            label: {},
        },
    ],
};

describe('localized', () => {
    it('falls back to the base language, every language, English, then any', () => {
        expect(localized({fr: 'Titre', _: 'Title'}, 'fr-CA')).toBe('Titre');
        expect(localized({fr: 'Titre', _: 'Title'}, 'de')).toBe('Title');
        expect(localized({en: 'Title', fr: 'Titre'}, 'de')).toBe('Title');
        expect(localized({fr: 'Titre'}, 'de')).toBe('Titre');
        expect(localized({}, 'de', 'key')).toBe('key');
    });
});

describe('values', () => {
    it('starts from the defaults, hidden fields left out', () => {
        expect(initialValues(definition, {title: 'Foo'})).toEqual({
            title: 'Foo',
            count: '',
            keywords: [],
            agreed: false,
        });
    });

    it('checks the values', () => {
        expect(
            validateValues(
                definition,
                {title: 'Too long', count: '4', keywords: [], agreed: false},
                messages
            )
        ).toEqual({
            title: ['max length 5'],
            count: ['max 3'],
            agreed: ['required'],
        });
        expect(
            validateValues(definition, {title: ' ', count: 'x'}, messages)
        ).toEqual({
            title: ['required'],
            count: ['number'],
            agreed: ['required'],
        });
    });

    it('normalizes the values to send', () => {
        expect(
            normalizeValues(definition, {
                title: ' Foo ',
                count: '2',
                keywords: ['a', ' '],
                agreed: true,
                origin: 'forged',
            })
        ).toEqual({title: 'Foo', count: 2, keywords: ['a'], agreed: true});
    });
});

describe('fields', () => {
    const attribute: DataboxField = {
        id: 'x',
        slug: 'keywords',
        name: 'Keywords',
        displayName: 'Keywords',
        label: {fr: 'Mots-clés'},
        databoxType: 'entity',
        type: 'entity',
        multiple: true,
        translatable: false,
        required: true,
        entityList: {id: 'list', name: 'Keywords'},
    };

    it('is created from a Databox attribute', () => {
        const field = fieldFromAttribute(attribute);
        expect(field).toMatchObject({
            source: 'attribute',
            key: 'keywords',
            type: 'entity',
            multiple: true,
            required: true,
            label: {fr: 'Mots-clés', _: 'Keywords'},
            attribute: {entityListId: 'list', slug: 'keywords'},
        });
        expect(widgetOf(field)).toBe('autocomplete');
        expect(widgetOf({...field, widget: 'radios'})).toBe('autocomplete');
        expect(widgetOf({...field, widget: 'checkboxes'})).toBe('checkboxes');
    });

    it('tells a field with settings of its own', () => {
        const fromAttribute = fieldFromAttribute(attribute);
        expect(isFieldCustomized(fromAttribute, [attribute])).toBe(false);
        expect(
            isFieldCustomized({...fromAttribute, help: {_: 'Some help'}}, [
                attribute,
            ])
        ).toBe(true);
        // The attribute it comes from is unknown
        expect(isFieldCustomized(fromAttribute, [])).toBe(true);

        const custom = newCustomField('text', ['text']);
        expect(isFieldCustomized(custom)).toBe(false);
        // Typed then erased, unchecked
        expect(
            isFieldCustomized({...custom, label: {fr: ''}, required: false})
        ).toBe(false);
        expect(isFieldCustomized({...custom, key: 'title'})).toBe(true);
        expect(isFieldCustomized({...custom, required: true})).toBe(true);

        const builtin = fieldFromBuiltin('databox_name');
        expect(isFieldCustomized(builtin)).toBe(false);
        expect(isFieldCustomized({...builtin, label: {_: 'File name'}})).toBe(
            true
        );

        expect(isFieldCustomized(newLayout('section'))).toBe(false);
        expect(
            isFieldCustomized({...newLayout('text'), label: {_: 'Hello'}})
        ).toBe(true);
    });

    it('gets a free key', () => {
        expect(newCustomField('text', ['text', 'text_2']).key).toBe('text_3');
    });

    it('reports the problems preventing the save', () => {
        expect(
            definitionProblems({
                version: 1,
                fields: [
                    {
                        id: '1',
                        source: 'custom',
                        key: 'a b',
                        type: 'text',
                        label: {},
                    },
                    {
                        id: '2',
                        source: 'custom',
                        key: 'k',
                        type: 'text',
                        label: {},
                    },
                    {
                        id: '3',
                        source: 'custom',
                        key: 'k',
                        type: 'text',
                        label: {},
                    },
                    {
                        id: '4',
                        source: 'custom',
                        key: 'c',
                        type: 'choice',
                        label: {},
                        choices: [],
                    },
                    {
                        id: '5',
                        source: 'custom',
                        key: 'h',
                        type: 'text',
                        label: {},
                        hidden: true,
                        required: true,
                    },
                ],
            })
        ).toEqual({
            1: 'invalid_key',
            3: 'duplicate_key',
            4: 'no_choice',
            5: 'hidden_required',
        });
    });
});
