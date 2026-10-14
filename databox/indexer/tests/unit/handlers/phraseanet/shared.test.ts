import Twig from 'twig';
import {
    attributeTypesEquivalence,
    createAsset,
    DataboxAttributeType,
    extractRenditionsFromEmbeds,
    extractRenditionsFromRecord,
    getStorySourceRecord,
    PhraseanetSearchType,
} from '../../../../src/handlers/phraseanet/shared';
import {
    CPhraseanetRecord,
    CPhraseanetStory,
} from '../../../../src/handlers/phraseanet/CPhraseanetRecord';
import {FieldMap} from '../../../../src/handlers/phraseanet/types';
import {
    fakePhraseanetClient,
    makeMetadata,
    makeRecord,
    makeStatusBit,
    makeStory,
    makeSubdef,
} from '../../../helpers/phraseanet';
import {createTestLogger, TestLogger} from '../../../helpers/logger';

let logger: TestLogger;

beforeEach(() => {
    logger = createTestLogger();
});

const cRecord = (overrides = {}) =>
    new CPhraseanetRecord(makeRecord(overrides), fakePhraseanetClient);

const cStory = (overrides = {}) =>
    new CPhraseanetStory(makeStory(overrides), fakePhraseanetClient);

describe('getStorySourceRecord', () => {
    it('returns the cover record when it is among the children', () => {
        const story = cStory({
            cover_record_id: 2,
            children: [
                makeRecord({record_id: '1'}),
                makeRecord({record_id: '2'}),
            ],
        });

        expect(getStorySourceRecord(story)!.record_id).toEqual('2');
    });

    it('falls back to the first child when the cover is not among them', () => {
        const story = cStory({
            cover_record_id: 99,
            children: [
                makeRecord({record_id: '1'}),
                makeRecord({record_id: '2'}),
            ],
        });

        expect(getStorySourceRecord(story)!.record_id).toEqual('1');
    });

    it('returns the first child when no cover is set', () => {
        const story = cStory({
            cover_record_id: null,
            children: [makeRecord({record_id: '7'})],
        });

        expect(getStorySourceRecord(story)!.record_id).toEqual('7');
    });

    it('treats cover_record_id 0 as set', () => {
        const story = cStory({
            cover_record_id: 0,
            children: [
                makeRecord({record_id: '0'}),
                makeRecord({record_id: '1'}),
            ],
        });

        expect(getStorySourceRecord(story)!.record_id).toEqual('0');
    });

    it('returns undefined for an empty story', () => {
        expect(getStorySourceRecord(cStory({children: []}))).toBeUndefined();
        expect(
            getStorySourceRecord(cStory({cover_record_id: 5, children: []}))
        ).toBeUndefined();
    });
});

describe('extractRenditionsFromRecord', () => {
    const record = () =>
        cRecord({
            phrasea_type: 'image',
            subdefs: [
                makeSubdef({name: 'preview', mime_type: 'image/jpeg'}),
                makeSubdef({name: 'thumbnail', mime_type: 'image/png'}),
            ],
        });

    it('maps each subdef to its configured renditions', () => {
        const out = extractRenditionsFromRecord(
            record(),
            {'image:preview': ['preview'], 'image:thumbnail': ['thumbnail']},
            true,
            logger
        );

        expect(out).toEqual([
            {
                name: 'preview',
                sourceFile: {
                    url: 'https://phraseanet.test/permalink/preview',
                    isPrivate: false,
                    importFile: true,
                    type: 'image/jpeg',
                },
            },
            {
                name: 'thumbnail',
                sourceFile: {
                    url: 'https://phraseanet.test/permalink/thumbnail',
                    isPrivate: false,
                    importFile: true,
                    type: 'image/png',
                },
            },
        ]);
    });

    it('emits several renditions from a single subdef', () => {
        const out = extractRenditionsFromRecord(
            record(),
            {'image:preview': ['main', 'preview']},
            false,
            logger
        );

        expect(out.map(r => r.name)).toEqual(['main', 'preview']);
        expect(out.every(r => r.sourceFile.importFile === false)).toBe(true);
    });

    it('skips subdefs with no mapping', () => {
        expect(
            extractRenditionsFromRecord(record(), {}, false, logger)
        ).toEqual([]);
    });

    it('keys the mapping on the phrasea type', () => {
        const out = extractRenditionsFromRecord(
            record(),
            {'video:preview': ['preview']},
            false,
            logger
        );

        expect(out).toEqual([]);
    });

    it('tolerates a record without subdefs', () => {
        expect(
            extractRenditionsFromRecord(
                cRecord({subdefs: undefined as any}),
                {'image:preview': ['preview']},
                false,
                logger
            )
        ).toEqual([]);
    });
});

describe('extractRenditionsFromEmbeds', () => {
    const embeds = [
        {
            name: 'preview',
            permalink: {url: 'https://phraseanet.test/embed/preview'},
            mime_type: 'image/jpeg',
        },
    ];

    it('maps embeds using the given phrasea type', () => {
        const out = extractRenditionsFromEmbeds(
            embeds,
            'image',
            {'image:preview': ['preview']},
            true,
            logger
        );

        expect(out).toEqual([
            {
                name: 'preview',
                sourceFile: {
                    url: 'https://phraseanet.test/embed/preview',
                    isPrivate: false,
                    importFile: true,
                    type: 'image/jpeg',
                },
            },
        ]);
    });

    it('skips unmapped embeds', () => {
        expect(
            extractRenditionsFromEmbeds(embeds, 'image', {}, false, logger)
        ).toEqual([]);
    });

    it('returns an empty list for no embeds', () => {
        expect(
            extractRenditionsFromEmbeds(
                [],
                'image',
                {'image:preview': ['preview']},
                false,
                logger
            )
        ).toEqual([]);
    });
});

describe('enums', () => {
    it('numbers the search types', () => {
        expect(PhraseanetSearchType.Record).toEqual(0);
        expect(PhraseanetSearchType.Story).toEqual(1);
    });

    it('maps phraseanet metadata types onto databox attribute types', () => {
        expect(attributeTypesEquivalence).toEqual({
            string: DataboxAttributeType.Text,
            date: DataboxAttributeType.Date,
            number: DataboxAttributeType.Number,
        });
    });
});

const fieldMap = (
    name: string,
    fm: Omit<Partial<FieldMap>, 'attributeDefinition'> & {
        values: FieldMap['values'];
        attributeDefinition?: Partial<FieldMap['attributeDefinition']>;
    }
): Record<string, FieldMap> => ({
    [name]: {
        id: `fm-${name}`,
        position: 0,
        type: DataboxAttributeType.Text,
        labels: {},
        ...fm,
        attributeDefinition: {
            id: `def-${name}`,
            multiple: false,
            name,
            ...(fm.attributeDefinition ?? {}),
        },
    } as FieldMap,
});

const build = (
    record: CPhraseanetRecord | CPhraseanetStory,
    overrides: {
        fieldMap?: Record<string, FieldMap>;
        tagIndex?: Record<number, string>;
        shortcuts?: {id: string; path: string}[];
        sourceSubdefName?: string;
        subdefToRendition?: Record<string, string[]>;
        importFiles?: boolean;
        isStory?: boolean;
    } = {}
) =>
    createAsset(
        'ws-1',
        overrides.importFiles ?? false,
        record,
        'records/a/b',
        'phraseanet',
        'idmp_asset_1_100',
        overrides.isStory ?? false,
        overrides.fieldMap ?? {},
        overrides.tagIndex ?? {},
        overrides.shortcuts ?? [],
        overrides.sourceSubdefName,
        overrides.subdefToRendition ?? {},
        logger
    );

describe('createAsset: envelope', () => {
    it('carries the identifiers and the phraseanet defaults', async () => {
        const asset = await build(cRecord({title: 'My record'}));

        expect(asset).toMatchObject({
            workspaceId: 'ws-1',
            key: 'idmp_asset_1_100',
            path: 'records/a/b',
            collectionKeyPrefix: 'phraseanet',
            name: 'My record',
            importFile: false,
            isPrivate: false,
            generateRenditions: false,
            isStory: false,
            attributes: [],
            tags: [],
            renditions: [],
            shortcutIntoCollections: [],
        });
        expect(asset.publicUrl).toBeUndefined();
    });

    it('forwards importFiles, isStory and the shortcut collections', async () => {
        const asset = await build(cRecord(), {
            importFiles: true,
            isStory: true,
            shortcuts: [{id: 'c1', path: '/classification/a'}],
        });

        expect(asset.importFile).toBe(true);
        expect(asset.isStory).toBe(true);
        expect(asset.shortcutIntoCollections).toEqual([
            {id: 'c1', path: '/classification/a'},
        ]);
    });
});

describe('createAsset: attributes', () => {
    it('reads a single-valued metadata field', async () => {
        const asset = await build(
            cRecord({
                metadata: [
                    makeMetadata('Titre', 'b'),
                    makeMetadata('Titre', 'a'),
                ],
            }),
            {
                fieldMap: fieldMap('Title', {
                    values: [{locale: 'fr', type: 'metadata', value: 'Titre'}],
                }),
            }
        );

        expect(asset.attributes).toEqual([
            {
                definitionId: 'def-Title',
                origin: 'machine',
                originVendor: 'indexer-import',
                locale: 'fr',
                position: 0,
                value: 'a ; b',
            },
        ]);
    });

    it('reads a multi-valued metadata field as a list', async () => {
        const asset = await build(
            cRecord({
                metadata: [
                    makeMetadata('Subject', 'b'),
                    makeMetadata('Subject', 'a'),
                ],
            }),
            {
                fieldMap: fieldMap('Subject', {
                    values: [{type: 'metadata', value: 'Subject'}],
                    attributeDefinition: {multiple: true},
                }),
            }
        );

        expect(asset.attributes![0]).toMatchObject({value: ['a', 'b']});
    });

    it('defaults a missing locale to null', async () => {
        const asset = await build(cRecord(), {
            fieldMap: fieldMap('Any', {
                values: [{type: 'text', value: 'literal'}],
            }),
        });

        expect(asset.attributes![0]).toMatchObject({
            locale: null,
            value: 'literal',
        });
    });

    it('renders a twig template and joins the lines for a single-valued field', async () => {
        const asset = await build(
            cRecord({
                metadata: [
                    makeMetadata('Subject', 'alpha'),
                    makeMetadata('Subject', 'beta'),
                ],
            }),
            {
                fieldMap: fieldMap('Subject', {
                    values: [
                        {
                            type: 'template',
                            value: '',
                            twig: Twig.twig({
                                data: "{% for v in record.getMetadata('Subject').values %}{{v}}\n{% endfor %}",
                            }),
                        },
                    ],
                }),
            }
        );

        expect(asset.attributes![0]).toMatchObject({
            value: 'alpha ; beta',
        });
    });

    it('keeps the rendered lines as a list for a multi-valued field', async () => {
        const asset = await build(
            cRecord({
                metadata: [
                    makeMetadata('Subject', 'alpha'),
                    makeMetadata('Subject', 'beta'),
                ],
            }),
            {
                fieldMap: fieldMap('Subject', {
                    values: [
                        {
                            type: 'template',
                            value: '',
                            twig: Twig.twig({
                                data: "{% for v in record.getMetadata('Subject').values %}{{v}}\n{% endfor %}",
                            }),
                        },
                    ],
                    attributeDefinition: {multiple: true},
                }),
            }
        );

        expect(asset.attributes![0]).toMatchObject({
            value: ['alpha', 'beta'],
        });
    });

    it('drops blank lines produced by a template', async () => {
        const asset = await build(cRecord(), {
            fieldMap: fieldMap('Any', {
                values: [
                    {
                        type: 'template',
                        value: '',
                        twig: Twig.twig({data: 'a\n\n   \nb\n'}),
                    },
                ],
                attributeDefinition: {multiple: true},
            }),
        });

        expect(asset.attributes![0]).toMatchObject({value: ['a', 'b']});
    });

    it('exposes getStatus to templates', async () => {
        const asset = await build(cRecord({status: [makeStatusBit(4, true)]}), {
            fieldMap: fieldMap('Flag', {
                values: [
                    {
                        type: 'template',
                        value: '',
                        twig: Twig.twig({
                            data: "{{ record.getStatus(4, 'on', 'off') }}",
                        }),
                    },
                ],
            }),
        });

        expect(asset.attributes![0]).toMatchObject({value: 'on'});
    });

    it('emits one attribute per value entry', async () => {
        const asset = await build(
            cRecord({
                metadata: [
                    makeMetadata('Titre', 'fr'),
                    makeMetadata('Title', 'en'),
                ],
            }),
            {
                fieldMap: fieldMap('Title', {
                    values: [
                        {locale: 'fr', type: 'metadata', value: 'Titre'},
                        {locale: 'en', type: 'metadata', value: 'Title'},
                    ],
                }),
            }
        );

        expect(asset.attributes).toHaveLength(2);
        expect(asset.attributes!.map((a: any) => a.value)).toEqual([
            'fr',
            'en',
        ]);
    });

    it('normalises a Number field through Number().toString()', async () => {
        const asset = await build(cRecord(), {
            fieldMap: fieldMap('Count', {
                type: DataboxAttributeType.Number,
                values: [{type: 'text', value: '007'}],
            }),
        });

        expect(asset.attributes![0]).toMatchObject({value: '7'});
    });

    it('serialises a Json field', async () => {
        const asset = await build(cRecord(), {
            fieldMap: fieldMap('Payload', {
                type: DataboxAttributeType.Json,
                values: [{type: 'text', value: {a: 1}}],
            }),
        });

        expect(asset.attributes![0]).toMatchObject({value: '{"a":1}'});
    });

    it('carries the field position', async () => {
        const asset = await build(cRecord(), {
            fieldMap: fieldMap('Any', {
                position: 3,
                values: [{type: 'text', value: 'x'}],
            }),
        });

        expect(asset.attributes![0]).toMatchObject({position: 3});
    });
});

describe('createAsset: tags', () => {
    it('adds the tag of every raised status bit', async () => {
        const asset = await build(
            cRecord({
                status: [
                    makeStatusBit(4, true),
                    makeStatusBit(5, false),
                    makeStatusBit(6, true),
                ],
            }),
            {tagIndex: {4: '/tags/a', 5: '/tags/b', 6: '/tags/c'}}
        );

        expect(asset.tags).toEqual(['/tags/a', '/tags/c']);
    });

    it('ignores bits that have no tag', async () => {
        const asset = await build(cRecord({status: [makeStatusBit(4, true)]}), {
            tagIndex: {},
        });

        expect(asset.tags).toEqual([]);
    });
});

describe('createAsset: source file and renditions', () => {
    const record = () =>
        cRecord({
            phrasea_type: 'image',
            subdefs: [
                makeSubdef({name: 'document', mime_type: 'image/tiff'}),
                makeSubdef({name: 'preview', mime_type: 'image/jpeg'}),
            ],
        });

    it('picks the public URL from the named source subdef', async () => {
        const asset = await build(record(), {sourceSubdefName: 'document'});

        expect(asset.publicUrl).toEqual(
            'https://phraseanet.test/permalink/document'
        );
    });

    it('leaves the public URL unset when the source subdef is absent', async () => {
        const asset = await build(record(), {sourceSubdefName: 'original'});

        expect(asset.publicUrl).toBeUndefined();
    });

    it('builds the renditions from the subdef mapping', async () => {
        const asset = await build(record(), {
            sourceSubdefName: 'document',
            subdefToRendition: {'image:preview': ['preview', 'thumbnail']},
            importFiles: true,
        });

        expect(asset.renditions).toEqual([
            {
                name: 'preview',
                sourceFile: {
                    url: 'https://phraseanet.test/permalink/preview',
                    isPrivate: false,
                    importFile: true,
                    type: 'image/jpeg',
                },
            },
            {
                name: 'thumbnail',
                sourceFile: {
                    url: 'https://phraseanet.test/permalink/preview',
                    isPrivate: false,
                    importFile: true,
                    type: 'image/jpeg',
                },
            },
        ]);
    });

    it('can use the source subdef as a rendition too', async () => {
        const asset = await build(record(), {
            sourceSubdefName: 'document',
            subdefToRendition: {'image:document': ['main']},
        });

        expect(asset.publicUrl).toEqual(
            'https://phraseanet.test/permalink/document'
        );
        expect(asset.renditions).toHaveLength(1);
    });

    it('tolerates a record without subdefs', async () => {
        const asset = await build(cRecord({subdefs: undefined as any}), {
            sourceSubdefName: 'document',
        });

        expect(asset.renditions).toEqual([]);
        expect(asset.publicUrl).toBeUndefined();
    });
});
