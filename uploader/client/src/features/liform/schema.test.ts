import {describe, expect, it} from 'vitest';
import {
    compileSchema,
    guessWidget,
    initialValues,
    normalizeValues,
    oneOfValue,
    selectedOneOf,
    sortedProperties,
    validateValues,
} from './schema';
import type {LiFormField} from './types';

describe('compileSchema', () => {
    it('resolves local references', () => {
        const schema: LiFormField = {
            definitions: {name: {type: 'string', title: 'Name'}},
            properties: {a: {$ref: '#/definitions/name', title: 'A'}},
        };
        expect(compileSchema(schema).properties!.a).toEqual({
            type: 'string',
            title: 'A',
        });
    });

    it('merges allOf without touching the given schema', () => {
        const schema: LiFormField = {
            properties: {
                a: {
                    defaultValue: 'x',
                    allOf: [{type: 'string'}, {title: 'A'}],
                },
            },
        };
        const before = JSON.stringify(schema);
        const compiled = compileSchema(schema);
        expect(compiled.properties!.a).toEqual({
            defaultValue: 'x',
            type: 'string',
            title: 'A',
        });
        expect(JSON.stringify(schema)).toBe(before);
        // Compiled twice, the default value is still there
        expect(compileSchema(schema).properties!.a.defaultValue).toBe('x');
    });

    it('follows chained references', () => {
        const schema: LiFormField = {
            definitions: {
                base: {type: 'string'},
                name: {$ref: '#/definitions/base', title: 'Name'},
            },
            properties: {a: {$ref: '#/definitions/name'}},
        };
        expect(compileSchema(schema).properties!.a).toEqual({
            type: 'string',
            title: 'Name',
        });
    });

    it('rejects circular references', () => {
        const schema: LiFormField = {
            definitions: {loop: {$ref: '#/definitions/loop'}},
            properties: {a: {$ref: '#/definitions/loop'}},
        };
        expect(() => compileSchema(schema)).toThrow();
    });
});

describe('guessWidget', () => {
    it('follows the LiForm precedence', () => {
        expect(guessWidget({widget: 'textarea', enum: ['a']})).toBe('textarea');
        expect(guessWidget({type: 'string', enum: ['a']})).toBe('choice');
        expect(guessWidget({oneOf: []})).toBe('oneOf');
        expect(guessWidget({type: 'string', format: 'email'})).toBe('email');
        expect(guessWidget({type: 'string', format: 'unknown'})).toBe('string');
        expect(
            guessWidget({
                type: 'array',
                uniqueItems: true,
                items: {enum: ['a']},
            })
        ).toBe('choice-multiple');
        expect(guessWidget({type: 'array', items: {type: 'string'}})).toBe(
            'array'
        );
        expect(guessWidget({})).toBe('object');
    });
});

describe('sortedProperties', () => {
    it('sorts on propertyOrder, keeping the order of equal ones', () => {
        const names = sortedProperties({
            properties: {
                c: {propertyOrder: 2},
                a: {},
                b: {},
                d: {propertyOrder: -1},
            },
        }).map(([n]) => n);
        expect(names).toEqual(['d', 'a', 'b', 'c']);
    });
});

describe('initialValues', () => {
    it('starts from the default values', () => {
        expect(
            initialValues({
                properties: {
                    title: {type: 'string', defaultValue: 'Hello'},
                    notify: {type: 'boolean'},
                    tags: {type: 'array', items: {type: 'string'}},
                    sub: {properties: {n: {type: 'number', defaultValue: 2}}},
                    empty: {type: 'string'},
                },
            })
        ).toEqual({
            title: 'Hello',
            notify: false,
            tags: [],
            sub: {n: 2},
        });
    });
});

describe('initialValues of a choice', () => {
    it('selects the first option when there is no empty one', () => {
        expect(
            initialValues({
                type: 'string',
                enum: ['a', 'b'],
                defaultValue: false,
            })
        ).toBe('a');
        expect(
            initialValues({type: 'string', enum: ['a', 'b'], defaultValue: 'b'})
        ).toBe('b');
    });
});

describe('normalizeValues', () => {
    const schema: LiFormField = {
        properties: {
            n: {type: 'number'},
            money: {type: 'number', widget: 'money'},
            pct: {type: 'string', widget: 'percent'},
            text: {type: 'string'},
            list: {type: 'array', items: {type: 'integer'}},
            choice: {
                oneOf: [
                    {title: 'A', properties: {a: {type: 'string'}}},
                    {title: 'B', properties: {b: {type: 'number'}}},
                ],
            },
        },
    };

    it('sends numbers as numbers and leaves empty values out', () => {
        expect(
            normalizeValues(schema, {
                n: '1.5',
                money: '12',
                pct: '',
                text: '',
                list: ['1', '', '3'],
                choice: {b: '4'},
            })
        ).toEqual({n: 1.5, money: 12, list: [1, 3], b: 4});
    });
});

describe('oneOf', () => {
    const field: LiFormField = {
        properties: {
            kind: {
                oneOf: [
                    {
                        title: 'A',
                        required: ['a'],
                        properties: {a: {type: 'string'}},
                    },
                    {title: 'B', properties: {b: {type: 'string'}}},
                ],
            },
        },
    };

    it('keeps the chosen option, even left empty', () => {
        const values = {kind: oneOfValue(field.properties!.kind, 1)};
        expect(selectedOneOf(field.properties!.kind, values.kind)).toBe(1);
        // The required field of the other option is not checked
        expect(validateValues(field, values)).toEqual({});
        expect(normalizeValues(field, values)).toEqual({});
    });

    it('sends the fields of the chosen option only', () => {
        expect(
            normalizeValues(field, {kind: {__option: 0, a: 'x', b: 'y'}})
        ).toEqual({a: 'x'});
        expect(validateValues(field, {kind: {__option: 0}})).toEqual({
            'kind.a': ['This value should not be blank.'],
        });
    });
});

describe('validateValues', () => {
    const schema: LiFormField = {
        required: ['title', 'agree', 'tags'],
        properties: {
            title: {type: 'string'},
            agree: {type: 'boolean'},
            tags: {type: 'array', items: {type: 'string'}},
            count: {type: 'integer'},
        },
    };

    it('reports the required fields and the bad numbers', () => {
        expect(
            Object.keys(
                validateValues(schema, {
                    title: '',
                    agree: false,
                    tags: [],
                    count: '1.5',
                })
            ).sort()
        ).toEqual(['agree', 'count', 'tags', 'title']);
    });

    it('accepts a filled form', () => {
        expect(
            validateValues(schema, {
                title: 'x',
                agree: true,
                tags: ['a'],
                count: '2',
            })
        ).toEqual({});
    });
});
