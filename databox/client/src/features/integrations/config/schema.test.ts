import {describe, expect, it} from 'vitest';
import {
    defaultsOf,
    errorsByPath,
    fromYaml,
    humanize,
    pruneConfig,
    toYaml,
    type ConfigObjectSchema,
} from './schema';

const schema: ConfigObjectSchema = {
    type: 'object',
    properties: {
        attribute: {type: 'string', required: true, widget: 'attribute'},
        rendition: {type: 'string', default: 'preview'},
        apiKey: {type: 'string', secret: true},
        maxImageSize: {type: 'integer', default: 2048, min: 256},
        processIncoming: {type: 'boolean', default: false},
        method: {type: 'enum', values: ['api', 'enqueue'], default: 'api'},
        labels: {
            type: 'object',
            toggle: 'enabled',
            properties: {
                enabled: {type: 'boolean', default: false},
                rendition: {type: 'string', widget: 'rendition'},
                threshold: {type: 'float', default: 0.5},
            },
        },
        position: {
            type: 'object',
            default: {top: '50%'},
            properties: {top: {type: 'string', default: '50%'}},
        },
        emails: {type: 'list', items: {type: 'string', widget: 'email'}},
        attributes: {
            type: 'list',
            items: {
                type: 'object',
                properties: {
                    name: {type: 'string', required: true},
                    threshold: {type: 'float'},
                },
            },
        },
        analyzers: {
            type: 'list',
            items: {
                type: 'object',
                discriminator: 'name',
                variants: {
                    checksum: {
                        type: 'object',
                        properties: {
                            name: {type: 'string', required: true},
                            algorithm: {type: 'string'},
                        },
                    },
                },
            },
        },
        options: {type: 'any'},
    },
};

describe('defaultsOf', () => {
    it('starts from the declared defaults, switched-off toggles and empty lists', () => {
        expect(defaultsOf(schema)).toEqual({
            rendition: 'preview',
            maxImageSize: 2048,
            processIncoming: false,
            method: 'api',
            labels: {enabled: false, threshold: 0.5},
            position: {top: '50%'},
            emails: [],
            attributes: [],
            analyzers: [],
        });
    });

    it('does not share its defaults between forms', () => {
        const a = defaultsOf(schema);
        const b = defaultsOf(schema);
        (a.position as {top: string}).top = '0';

        expect((b.position as {top: string}).top).toBe('50%');
    });
});

describe('pruneConfig', () => {
    it('keeps the required keys and drops the optional empty ones', () => {
        expect(
            pruneConfig(
                {
                    attribute: '',
                    rendition: '',
                    apiKey: undefined,
                    maxImageSize: 1024,
                    processIncoming: false,
                    labels: {enabled: false, rendition: ''},
                    position: {},
                    emails: [],
                    attributes: [{name: 'tags', threshold: undefined}, {}],
                    analyzers: [{name: 'checksum', algorithm: ''}],
                    options: {},
                },
                schema
            )
        ).toEqual({
            attribute: '',
            maxImageSize: 1024,
            processIncoming: false,
            labels: {enabled: false},
            attributes: [{name: 'tags'}],
            analyzers: [{name: 'checksum'}],
        });
    });

    it('leaves what the schema does not describe alone', () => {
        expect(pruneConfig({options: {foo: ''}, extra: 'x'}, schema)).toEqual({
            options: {foo: ''},
            extra: 'x',
        });
    });
});

describe('toYaml / fromYaml', () => {
    it('round-trips a configuration', () => {
        const values = {
            attribute: 'faces',
            labels: {enabled: true, threshold: 0.5},
            emails: ['a@b.c'],
        };

        expect(fromYaml(toYaml(values))).toEqual(values);
    });

    it('turns an empty configuration into an empty document, and back', () => {
        expect(toYaml({})).toBe('');
        expect(fromYaml('')).toEqual({});
        expect(fromYaml('# nothing\n')).toEqual({});
    });

    it('rejects what is not a mapping', () => {
        expect(() => fromYaml('- a\n- b')).toThrow(/mapping/);
        expect(() => fromYaml('a: [')).toThrow();
    });
});

describe('errorsByPath', () => {
    it('indexes the violations by configuration path', () => {
        expect(
            errorsByPath({
                violations: [
                    {propertyPath: 'config.minConfidence', message: 'Too big'},
                    {
                        propertyPath: 'config.labels.rendition',
                        message: 'Unknown',
                    },
                    {propertyPath: 'config', message: 'Missing "attribute"'},
                    {propertyPath: 'name', message: 'Too long'},
                ],
            })
        ).toEqual({
            'minConfidence': 'Too big',
            'labels.rendition': 'Unknown',
            '_global': 'Missing "attribute"\nToo long',
        });
    });
});

describe('humanize', () => {
    it('labels a key like the API does', () => {
        expect(humanize('maxImageSize')).toBe('Max image size');
        expect(humanize('actions_on_reject')).toBe('Actions on reject');
    });
});
