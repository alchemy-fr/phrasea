import {describe, expect, it} from 'vitest';
import fixture from '../../../../../api/tests/fixtures/liform-schema.json';
import type {LiFormSchema} from '@/features/liform/types';
import type {DataboxField, InputField} from '../types';
import {fromLiForm} from './fromLiForm';

const databoxFields: DataboxField[] = [
    {
        id: 'a1',
        slug: 'description',
        name: 'Description',
        displayName: 'Description',
        label: {fr: 'Légende'},
        databoxType: 'textarea',
        type: 'textarea',
        multiple: false,
        translatable: true,
        required: false,
        entityList: null,
    },
    {
        id: 'a2',
        slug: 'transport',
        name: 'Transport',
        displayName: 'Transport',
        label: {},
        databoxType: 'entity',
        type: 'entity',
        multiple: true,
        translatable: false,
        required: false,
        entityList: {id: 'list-1', name: 'Transports'},
    },
    {
        id: 'a3',
        slug: 'shot_at',
        name: 'Shot at',
        displayName: 'Shot at',
        label: {},
        databoxType: 'number',
        type: 'number',
        multiple: false,
        translatable: false,
        required: false,
        entityList: null,
    },
];

const inputs = (fields: unknown[]) => fields as InputField[];

describe('fromLiForm', () => {
    it('converts the fixture of the API', () => {
        const {definition, warnings} = fromLiForm(
            {locale: 'en', schema: fixture as LiFormSchema},
            databoxFields,
            'ws'
        );
        const fields = inputs(definition.fields);

        expect(definition.workspaceId).toBe('ws');
        expect(fields.map(f => [f.key, f.source, f.type])).toEqual([
            ['album', 'custom', 'text'],
            ['dest', 'custom', 'choice'],
            ['description', 'attribute', 'textarea'],
            ['agreed', 'custom', 'boolean'],
        ]);
        expect(fields[0]).toMatchObject({required: true, label: {en: 'Album'}});
        expect(fields[1].choices).toEqual([
            {value: 'One', label: {en: 'One'}},
            {value: 'Two', label: {en: 'Two'}},
        ]);
        expect(fields[2]).toMatchObject({
            label: {en: 'Description', fr: 'Légende', _: 'Description'},
            attribute: {slug: 'description', translatable: true},
        });
        expect(warnings).toEqual([
            {key: 'album', code: 'unknown_attribute'},
            {key: 'dest', code: 'unknown_attribute'},
            {key: 'agreed', code: 'unknown_attribute'},
        ]);
    });

    it('maps the Databox keys and widgets', () => {
        const schema: LiFormSchema = {
            type: 'object',
            properties: {
                transport: {
                    type: 'array',
                    widget: 'choice-multiple-expanded',
                    items: {
                        type: 'string',
                        enum: ['u1', 'u2'],
                        enum_titles: ['Bike', 'Boat'],
                    },
                },
                databox_tags: {
                    type: 'array',
                    items: {type: 'string', enum: ['t1']},
                },
                __notify: {type: 'boolean', title: 'Notify me'},
                shot_at: {type: 'string', format: 'date'},
                keywords: {type: 'array', items: {type: 'string'}},
                when: {type: 'string', widget: 'compatible-datetime'},
                nested: {type: 'object', properties: {}},
                choice: {oneOf: [{title: 'A'}, {title: 'B'}]},
            },
        };

        const {definition, warnings} = fromLiForm(
            {locale: null, schema},
            databoxFields,
            'ws'
        );
        const fields = inputs(definition.fields);

        expect(
            fields.map(f => [
                f.key,
                f.source,
                f.type,
                f.multiple ?? false,
                f.widget,
            ])
        ).toEqual([
            ['transport', 'attribute', 'entity', true, 'checkboxes'],
            ['databox_tags', 'builtin', 'tag', true, 'autocomplete'],
            ['__notify', 'builtin', 'boolean', false, undefined],
            ['shot_at', 'custom', 'date', false, undefined],
            ['keywords', 'custom', 'text', true, undefined],
            ['when', 'custom', 'date_time', false, undefined],
        ]);
        expect(fields[0].attribute?.entityListId).toBe('list-1');
        expect(fields[2].label).toEqual({_: 'Notify me'});
        expect(warnings).toEqual([
            {key: 'transport', code: 'restricted_entities'},
            {key: 'shot_at', code: 'type_mismatch'},
            {key: 'keywords', code: 'unknown_attribute'},
            {key: 'when', code: 'unknown_attribute'},
            {key: 'nested', code: 'unsupported'},
            {key: 'choice', code: 'unsupported'},
        ]);
    });

    it('merges the titles of the other locales', () => {
        const en: LiFormSchema = {
            properties: {
                dest: {
                    type: 'string',
                    title: 'Destination',
                    enum: ['a'],
                    enum_titles: ['Archive'],
                },
            },
        };
        const fr: LiFormSchema = {
            properties: {
                dest: {
                    type: 'string',
                    title: 'Destination FR',
                    description: 'Aide',
                    enum: ['a'],
                    enum_titles: ['Archives'],
                },
                other: {type: 'string', title: 'Ignored'},
            },
        };

        const {definition} = fromLiForm({locale: 'en', schema: en}, [], null, [
            {locale: 'fr', schema: fr},
        ]);

        expect(definition.fields).toHaveLength(1);
        expect(definition.fields[0]).toMatchObject({
            label: {en: 'Destination', fr: 'Destination FR'},
            help: {fr: 'Aide'},
            choices: [{value: 'a', label: {en: 'Archive', fr: 'Archives'}}],
        });
    });
});
