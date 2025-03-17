import {IndexIterator} from '../../indexers';
import {
    ConfigDataboxMapping,
    ConfigPhraseanetSubdef,
    FieldMap,
    PhraseanetConfig,
    PhraseanetDatabox,
    PhraseanetSubdefStruct,
} from './types';
import {CPhraseanetRecord, CPhraseanetStory} from './CPhraseanetRecord';
import PhraseanetClient from './phraseanetClient';
import {
    AttrClassIndex,
    attributeTypesEquivalence,
    createAsset,
    DataboxAttributeType,
    TagIndex,
} from './shared';
import {getConfig, getStrict} from '../../configLoader';
import {escapePath, escapeSlashes, splitPath} from '../../lib/pathUtils';
import {AttributeDefinition, Tag} from '../../databox/types';
import Twig from 'twig';
import {Logger} from 'winston';
import {DataboxClient} from '../../databox/client';
import Yaml from 'js-yaml';
import util from 'util';
import p from 'path';
import {collectionBasedOnPathStrategy} from '../../databox/strategy/collectionBasedOnPathStrategy.ts';
import {getAlternateUrls} from '../../alternateUrl.ts';

export const phraseanetIndexer: IndexIterator<PhraseanetConfig> =
    async function* (location, logger, databoxClient, options) {
        Twig.extendFilter('escapePath', function (v: string, args: any) {
            return escapePath(v, args[0] ?? '_');
        });

        const phraseanetClient = new PhraseanetClient(location.options, logger);

        const databoxMapping: ConfigDataboxMapping[] = getStrict(
            'databoxMapping',
            location.options
        );
        const importFiles: boolean = getConfig(
            'importFiles',
            false,
            location.options
        );

        const idempotencePrefixes: Record<string, string> = {};
        for (const k of [
            'asset',
            'collection',
            'attributeDefinition',
            'renditionDefinition',
        ]) {
            idempotencePrefixes[k] = getConfig(
                `idempotencePrefixes.${k}`,
                phraseanetClient.getId() + '_',
                location.options
            );
        }

        for (const dm of databoxMapping) {
            const phraseanetDatabox = await phraseanetClient.getDatabox(
                dm.databox
            );
            if (phraseanetDatabox === undefined) {
                logger.info(`Unknown databox "${dm.databox}" (ignored)`);
                continue;
            }

            logger.info(
                `Start indexing databox "${phraseanetDatabox.name}" (#${phraseanetDatabox.databox_id}) to workspace "${dm.workspaceSlug}"`
            );

            // scan the conf.fieldMap to get a list of required locales
            const fieldMap = new Map<string, FieldMap>(
                Object.entries(dm.fieldMap ?? {})
            );
            let locales: string[] = [];
            for (const [_name, fm] of fieldMap) {
                for (const v of fm.values) {
                    if (v.locale !== undefined) {
                        locales.push(v.locale);
                    }
                }
            }
            locales = locales.filter((value, index, a) => {
                return a.indexOf(value) === index;
            });

            let workspaceId =
                await databoxClient.getOrCreateWorkspaceIdWithSlug(
                    dm.workspaceSlug,
                    locales
                );

            if (options.createNewWorkspace) {
                logger.info(`Flushing databox workspace "${dm.workspaceSlug}"`);
                workspaceId = await databoxClient.flushWorkspace(workspaceId);
            }

            const attrClassIndex: AttrClassIndex = {};
            const defaultPublicClass = 'public';
            const name = 'Phraseanet Public';
            logger.info(`Creating "${name}" attribute class`);
            attrClassIndex[defaultPublicClass] =
                await databoxClient.createAttributeClass(defaultPublicClass, {
                    name,
                    public: true,
                    editable: true,
                    workspace: `/workspaces/${workspaceId}`,
                    key: defaultPublicClass,
                });

            logger.info(`Importing metadata structure`);
            await importMetadataStructure(
                databoxClient,
                workspaceId,
                phraseanetDatabox.databox_id,
                phraseanetClient,
                dm,
                fieldMap,
                idempotencePrefixes['attributeDefinition'],
                attrClassIndex[defaultPublicClass]['@id'],
                logger
            );

            logger.info(`Importing status-bits structure`);
            const tagIndex = await importStatusBitsStructure(
                databoxClient,
                workspaceId,
                phraseanetDatabox.databox_id,
                phraseanetClient,
                logger
            );

            logger.info(`Importing subdefs structure`);
            const subdefToRendition = await importSubdefsStructure(
                databoxClient,
                workspaceId,
                phraseanetDatabox.databox_id,
                phraseanetClient,
                dm,
                idempotencePrefixes['renditionDefinition'],
                logger
            );

            const collectionKeyPrefix =
                idempotencePrefixes['collection'] +
                phraseanetDatabox.databox_id +
                ':';

            const recordsCollectionPath: string =
                dm.recordsCollectionPath ?? '';
            let recordsCollectionPathTwig: Twig.Template | null = null;
            if (recordsCollectionPath.search(/\{(\{|%)/) !== -1) {
                recordsCollectionPathTwig = Twig.twig({
                    data: recordsCollectionPath,
                });
            }

            const storiesCollectionPath: string =
                dm.storiesCollectionPath ?? '';
            let importStories = false;
            let storiesCollectionPathTwig: Twig.Template | null = null;
            if (dm.storiesCollectionPath !== undefined) {
                if (storiesCollectionPath.search(/\{(\{|%)/) !== -1) {
                    storiesCollectionPathTwig = Twig.twig({
                        data: storiesCollectionPath,
                    });
                }
                importStories = true;
            }

            const sourceCollections = await getSourceCollections(
                phraseanetDatabox,
                dm,
                logger
            );
            const searchParams = {
                bases: sourceCollections, // if empty (no collections on config) : search all collections
            };

            const recordStories: Record<string, {id: string; path: string}[]> =
                {}; // key: record_id ; values: story_id's
            if (importStories) {
                logger.info(`Importing stories`);
                let stories: CPhraseanetStory[] = [];
                let offset = 0;
                do {
                    stories = await phraseanetClient.searchStories(
                        searchParams,
                        offset,
                        20,
                        ''
                    );
                    for (const s of stories) {
                        const storyPathParts: string[] = splitPath(
                            storiesCollectionPathTwig
                            ? await storiesCollectionPathTwig.renderAsync({
                                record: s,
                                collection: phraseanetDatabox.collections[s.base_id],
                            })
                            : storiesCollectionPath
                        );

                        const storyPath = '/' + storyPathParts.join('/');

                        // create the base
                        let storyParent: string | undefined = undefined;
                        if (storyPathParts.length > 0) {
                            storyParent =
                                '/collections/' +
                                (await databoxClient.createCollectionTreeBranch(
                                    workspaceId,
                                    collectionKeyPrefix,
                                    storyPathParts.map(k => ({
                                        key: k,
                                        title: k,
                                    }))
                                ));
                        }
                        // then create the story collection
                        const storyCollId =
                            await databoxClient.createCollection(
                                s.resource_id,
                                {
                                    workspaceId: workspaceId,
                                    key:
                                        idempotencePrefixes['collection'] +
                                        s.databox_id +
                                        '_' +
                                        s.story_id,
                                    title: s.title,
                                    parent: storyParent,
                                }
                            );

                        logger.info(
                            `  Phraseanet story "${s.title}" (#${
                                s.story_id
                            }) from base "${
                                phraseanetDatabox.collections[s.base_id].name
                            }" (#${s.base_id}) ==> collection (#${storyCollId})`
                        );

                        const story_title = escapeSlashes((s.title ?? "story_" + s.databox_id + '_' + s.story_id).trim().substring(0, 50));
                        logger.info(`creating story asset for story ${storyPath + '/' + story_title}`);
                        yield createAsset(
                            workspaceId,
                            importFiles,
                            s,
                            storyPath + '/' + story_title,
                            collectionKeyPrefix,
                            idempotencePrefixes['asset'] +
                                s.databox_id +
                                '_' +
                                s.story_id,
                            '/collections/' + storyCollId,
                            fieldMap,
                            tagIndex,
                            [],
                            dm.sourceFile,
                            subdefToRendition,
                            logger
                        );

                        for await (const child_rid of phraseanetClient.getStoryChildren(
                            s.databox_id,
                            s.story_id
                        )) {
                            if (recordStories[child_rid] === undefined) {
                                recordStories[child_rid] = [];
                            }
                            recordStories[child_rid].push({
                                id: storyCollId,
                                path: storyPath + '/' + s.title,
                            });
                        }
                    }
                    offset += stories.length;
                } while (stories.length == 20);
            }

            logger.info(`Importing records`);
            let records: CPhraseanetRecord[];
            let offset = 0;
            do {
                records = await phraseanetClient.searchRecords(
                    searchParams,
                    offset,
                    50,
                    dm.searchQuery ?? ''
                );
                for (const record of records) {
                    logger.info(
                        `Phraseanet record "${record.title}" (#${
                            record.record_id
                        }) from base "${
                            phraseanetDatabox.collections[record.base_id].name
                        }" (#${record.base_id})`
                    );

                    const copyTo = recordStories[record.record_id] ?? [];

                    // copy the asset to other location(s) ?
                    for (const ct of dm.copyTo ?? []) {
                        const template = Twig.twig({data: ct});
                        const paths = (
                            await template.renderAsync({
                                record: record,
                                collection:
                                    phraseanetDatabox.collections[
                                        record.base_id
                                    ],
                            })
                        )
                            .split('\n')
                            .map((p: string) => p.trim())
                            .filter((p: string) => p);

                        for (const path of paths) {
                            const branch = splitPath(path);
                            const collId =
                                await databoxClient.createCollectionTreeBranch(
                                    workspaceId,
                                    collectionKeyPrefix,
                                    branch.map(k => ({
                                        key: k,
                                        title: k,
                                    }))
                                );
                            copyTo.push({
                                id: collId,
                                path: path,
                            });
                        }
                    }

                    let path: string = '';
                    if (recordsCollectionPathTwig !== null) {
                        path = await recordsCollectionPathTwig.renderAsync({
                            record: record,
                            collection:
                                phraseanetDatabox.collections[record.base_id],
                        });
                    } else {
                        // bc: dispatch in original phraseanet collection.name
                        path = `${recordsCollectionPath}/${escapeSlashes(phraseanetDatabox.collections[record.base_id].name)}`;
                    }
                    path += '/' + escapeSlashes(record.original_name);

                    yield createAsset(
                        workspaceId,
                        importFiles,
                        record,
                        path,
                        collectionKeyPrefix,
                        idempotencePrefixes['asset'] +
                            record.databox_id +
                            '_' +
                            record.record_id,
                        null,
                        fieldMap,
                        tagIndex,
                        copyTo,
                        dm.sourceFile,
                        subdefToRendition,
                        logger
                    );
                }
                offset += records.length;
            } while (records.length == 50);
        }
    };

async function getSourceCollections(
    phraseanetDatabox: PhraseanetDatabox,
    dm: ConfigDataboxMapping,
    logger: Logger
): Promise<string[]> {
    const sourceCollections: string[] = [];
    if (dm.collections) {
        for (const c of dm.collections.split(',')) {
            const collection = phraseanetDatabox.collections[c.trim()];
            if (collection == undefined) {
                logger.info(
                    `Unknown collection "${c.trim()}" into databox "${
                        phraseanetDatabox.name
                    }" (#${phraseanetDatabox.databox_id}) (ignored)`
                );
                continue;
            }
            sourceCollections.push(collection.base_id.toString());
        }
        if (sourceCollections.length === 0) {
            logger.info(
                `No collection found for "${dm.collections}" into databox "${phraseanetDatabox.name}" (#${phraseanetDatabox.databox_id}) (databox ignored)`
            );
        }
    } else {
        for (const baseId of phraseanetDatabox.baseIds) {
            sourceCollections.push(baseId);
        }
    }

    return sourceCollections;
}

async function importSubdefsStructure(
    databoxClient: DataboxClient,
    workspaceId: string,
    phraseanetDataboxId: string,
    phraseanetClient: PhraseanetClient,
    dm: ConfigDataboxMapping,
    idempotencePrefix: string,
    logger: Logger
): Promise<Record<string, string[]>> {
    const classIndex: Record<string, string> = {};
    const renditionClasses =
        await databoxClient.getRenditionClasses(workspaceId);
    renditionClasses.forEach(rc => {
        classIndex[rc.name] = rc.id;
    });

    const subdefs =
        await phraseanetClient.getSubdefsStruct(phraseanetDataboxId);
    const sdByName: Record<
        string,
        {
            name: string;
            parent: string | null;
            buildMode: number;
            useAsOriginal: boolean;
            useAsPreview: boolean;
            useAsThumbnail: boolean;
            useAsThumbnailActive: boolean;
            types: Record<string, PhraseanetSubdefStruct>;
            class: string | null;
            labels: Record<string, string>;
        }
    > = {};

    if (dm.renditions === false) {
        // special value: do not create rendition definitions
        return {};
    }

    if (dm.sourceFile === undefined) {
        dm.sourceFile = 'document';
    }
    if (dm.renditions === undefined) {
        // import all subdefs from phraseanet
        dm['renditions'] = {
            original: {
                useAsOriginal: true,
                buildMode: 1,
                class: 'original',
            } as ConfigPhraseanetSubdef,
        };

        for (const sd of subdefs) {
            if (!dm.renditions[sd.name]) {
                const o: any = {
                    class: sd.class,
                    buildMode: 2,
                    parent: 'original',
                };
                if (sd.name === 'preview') {
                    o['useAsPreview'] = true;
                }
                if (sd.name === 'thumbnail') {
                    o['useAsThumbnail'] = true;
                }
                if (sd.name === 'thumbnailgif') {
                    o['useAsThumbnailActive'] = true;
                }
                o.builders = {};

                dm.renditions[sd.name] = o;
            }
            dm.renditions[sd.name].builders[sd.type] = {
                from: `${sd.type}:${sd.name}`,
            };
        }

        const sampleConfig = util
            .inspect(
                {
                    sourceFile: dm.sourceFile,
                    renditions: dm.renditions,
                },
                {depth: null, compact: false}
            )
            .replace(/^\{(.*)}$/s, '...$1...');
        logger.info(
            `No renditions block in config. Importing all subdefs from phraseanet using generated config:\n${sampleConfig}`
        );
    }

    const subdefToRendition = {} as Record<string, string[]>;

    for (const [name, rendition] of Object.entries(dm.renditions)) {
        if (!sdByName[name]) {
            sdByName[name] = {
                name: name,
                parent: rendition['parent'] ?? null,
                useAsOriginal: rendition['useAsOriginal'] ?? false,
                buildMode:
                    rendition['buildMode'] ?? (rendition['builders'] ? 2 : 1),
                useAsPreview: rendition['useAsPreview'] ?? false,
                useAsThumbnail: rendition['useAsThumbnail'] ?? false,
                useAsThumbnailActive:
                    rendition['useAsThumbnailActive'] ?? false,
                types: {} as Record<string, PhraseanetSubdefStruct>,
                class: rendition['class'] ?? null,
                labels: {},
            };
        }

        for (const [family, settings] of Object.entries(
            rendition['builders'] ?? []
        )) {
            if ('build' in settings && 'from' in settings) {
                logger.error(
                    `  Rendition-definition "${name}" for family "${family}": Use "build" OR "from", not both. Rendition definition ignored`
                );
                continue;
            }
            if ('build' in settings) {
                // hardcoded
            }
            if ('from' in settings) {
                // find the subdef with good name and family
                const [sdFamily, sdName] = settings['from'].split(':');
                const sd = subdefs.find(
                    sd => sd.name === sdName && sd.type === sdFamily
                );
                if (!sd) {
                    logger.error(`  Subdef "${settings['from']}" not found`);
                    continue;
                }
                if (sdByName[name].types[sd.type]) {
                    logger.error(
                        `  Build "${sd.type}" for rendition "${name}" already set`
                    );
                    continue;
                }
                if (!subdefToRendition[settings['from']]) {
                    subdefToRendition[settings['from']] = [];
                }
                subdefToRendition[settings['from']].push(name);
                sdByName[name].types[sd.type] = sd;
                sdByName[name].labels = sd.labels; // todo: check conflicts
                if (!rendition['class']) {
                    // use phrnet class
                    if (sdByName[name].class === null) {
                        sdByName[name].class = sd.class;
                    }
                    // sd of same name should have the same class
                    if (
                        sdByName[name].class !== sd.class &&
                        sdByName[name].class !== 'mixed'
                    ) {
                        logger.info(
                            `  Rendition "${name}" gets different class ("${sdByName[sd.name].class}" and "${sd.class}": "mixed" is used)`
                        );
                        sdByName[name].class = 'mixed';
                    }
                }
            }
        }
    }

    const renditionIdByName = {} as Record<string, string>;

    for (const sdName in sdByName) {
        const sd = sdByName[sdName];

        if (!sd.class) {
            logger.info(
                `  Rendition definition "${sdName}" has neither "class" or phraseanet "from": using class "public"`
            );
            sd.class = 'public';
        }

        if (!classIndex[sd.class]) {
            logger.info(`  Creating rendition class "${sd.class}" `);
            classIndex[sd.class] = await databoxClient.createRenditionClass({
                name: sd.class,
                workspace: `/workspaces/${workspaceId}`,
                public: true,
            });
        }

        logger.info(
            `  Creating rendition definition "${sd.name}" of class "${sd.class}"`
        );
        const jsConf: Record<string, object> = {};
        const translators: Record<string, typeof translateImageSettings> = {
            image: translateImageSettings,
            video: translateVideoSettings,
            audio: translateAudioSettings,
            document: translateDocumentSettings,
        };
        for (const family in sd.types) {
            if (translators[family]) {
                jsConf[family] = translators[family](sd.types[family]);
            }
        }

        if (sd['parent'] && !renditionIdByName[sd['parent']]) {
            logger.error(
                `    Parent rendition definition "${sd['parent']}" for "${sd.name}" not found: no parent set. Check declaration order`
            );
            sd['parent'] = null;
        }

        renditionIdByName[sd.name] =
            await databoxClient.createRenditionDefinition({
                name: sd.name,
                parent: sd['parent']
                    ? `/rendition-definitions/${renditionIdByName[sd['parent']]}`
                    : null,
                key: `${idempotencePrefix}${sd.name}`,
                class: `/rendition-classes/${classIndex[sd.class]}`,
                buildMode: sd.buildMode,
                useAsOriginal: sd.useAsOriginal,
                useAsPreview: sd.useAsPreview,
                useAsThumbnail: sd.useAsThumbnail,
                useAsThumbnailActive: sd.name === 'thumbnailgif',
                priority: 0,
                workspace: `/workspaces/${workspaceId}`,
                labels: {
                    phraseanetDefinition: sd.labels,
                },
                definition: Yaml.dump(jsConf, {lineWidth: 100}).trim(),
            });
    }

    return subdefToRendition;
}

async function importStatusBitsStructure(
    databoxClient: DataboxClient,
    workspaceId: string,
    phraseanetDataboxId: string,
    phraseanetClient: PhraseanetClient,
    logger: Logger
): Promise<TagIndex> {
    const tagIndex: TagIndex = {};
    for (const sb of await phraseanetClient.getStatusBitsStruct(
        phraseanetDataboxId
    )) {
        logger.info(`  Creating "${sb.label_on}" tag`);
        const key =
            phraseanetClient.getId() +
            '_' +
            phraseanetDataboxId +
            '.sb' +
            sb.bit;
        const tag: Tag = await databoxClient.createTag(key, {
            workspace: `/workspaces/${workspaceId}`,
            name: sb.label_on,
        });
        tagIndex[sb.bit] = '/tags/' + tag.id;
    }

    return tagIndex;
}

async function importMetadataStructure(
    databoxClient: DataboxClient,
    workspaceId: string,
    phraseanetDataboxId: string,
    phraseanetClient: PhraseanetClient,
    dm: ConfigDataboxMapping,
    fieldMap: Map<string, FieldMap>,
    idempotencePrefix: string,
    attrClass: string,
    logger: Logger
): Promise<void> {
    const metaStructure =
        await phraseanetClient.getMetaStruct(phraseanetDataboxId);
    if (!dm.fieldMap) {
        // import all fields from structure
        for (const name in metaStructure) {
            fieldMap.set(name, {
                id: metaStructure[name].id,
                position: 0,
                type:
                    attributeTypesEquivalence[metaStructure[name].type] ??
                    DataboxAttributeType.Text,
                multivalue: metaStructure[name].multivalue,
                readonly: metaStructure[name].readonly,
                translatable: false,
                labels: metaStructure[name].labels,
                values: [
                    {
                        type: 'metadata',
                        value: name,
                    },
                ],
                attributeDefinition: {} as AttributeDefinition,
            });
        }
    }
    const attributeDefinitionIndex: Record<string, AttributeDefinition> = {};
    let ufid = 0; // used to generate a unique id for fields declared in conf, but not existing in phraseanet
    let position = 1;
    for (const [name, fm] of fieldMap) {
        fm.id = metaStructure[name]
            ? metaStructure[name].id
            : (--ufid).toString();
        fm.position = position++;
        fm.multivalue =
            fm.multivalue ??
            (metaStructure[name] ? metaStructure[name].multivalue : false);
        fm.readonly =
            fm.readonly ??
            (metaStructure[name] ? metaStructure[name].readonly : false);
        fm.labels =
            fm.labels ??
            (metaStructure[name] ? metaStructure[name].labels : {});
        fm.type =
            fm.type ??
            (metaStructure[name]
                ? attributeTypesEquivalence[metaStructure[name].type]
                : DataboxAttributeType.Text);
        for (const v of fm.values) {
            if (v.locale !== undefined) {
                fm.translatable = true;
            }

            if (v.type === 'template') {
                try {
                    v.twig = Twig.twig({data: v.value}); // compile once
                } catch (e: any) {
                    throw new Error(
                        `Error compiling twig for field "${name}": ${e.message}`
                    );
                }
            }
        }

        if (!attributeDefinitionIndex[name]) {
            const data = {
                key: `${idempotencePrefix}_${name}_${fm.type}_${fm.multivalue ? '1' : '0'}`,
                name: name,
                position: fm.position,
                editable: !fm.readonly,
                multiple: fm.multivalue,
                fieldType: attributeTypesEquivalence[fm.type ?? ''] || fm.type,
                workspace: `/workspaces/${workspaceId}`,
                class: attrClass,
                labels: fm.labels,
                translatable: fm.translatable,
            };
            logger.info(`  Creating "${name}" attribute definition`);
            attributeDefinitionIndex[name] =
                await databoxClient.createAttributeDefinition(fm.id, data);
        }
        fm.attributeDefinition = attributeDefinitionIndex[name];
    }
}

function translateDocumentSettings(sd: PhraseanetSubdefStruct): object {
    // too bad: phraseanet api does not provide the target "mediatype" (image, video, ...)
    // so we guess from the presence of option "icodec"
    if (sd.options['icodec']) {
        return translateDocumentSettings_withIcodec(sd);
    }
    // here no icodec: pdf or flexpaper (flexpaper is not handled by phrasea, so import as pdf)
    return translateDocumentSettings_toPdf();
}

function translateDocumentSettings_withIcodec(
    sd: PhraseanetSubdefStruct
): object {
    return {
        transformations: [
            {
                module: 'document_to_pdf',
            },
            {
                module: 'pdf_to_image',
                options: {
                    size: [sd.options['size'], sd.options['size']],
                    resolution: sd.options['resolution'],
                    extension: sd.options['icodec'],
                },
            },
        ],
    };
}

function translateDocumentSettings_toPdf(): object {
    return {
        transformations: [
            {
                module: 'document_to_pdf',
            },
        ],
    };
}

function translateImageSettings(sd: PhraseanetSubdefStruct): object {
    // todo: extension ?
    const size = sd.options['size'];
    const icodecFormats: Record<string, string> = {
        jpeg: 'jpeg',
        png: 'png',
        tiff: 'tiff',
    };
    const format: string = icodecFormats[sd.options.icodec] ?? '';
    if (!format) {
        throw new Error(
            `Unsupported image codec: ${sd.options.icodec} for subdef image:${sd.name}`
        );
    }

    const filters: Record<string, any> = {
        auto_rotate: null,
    };

    const bgcolor = sd.options.backgroundcolor?.['0'] ?? '';
    if (bgcolor) {
        filters.background_fill = {
            color: bgcolor,
            opacity: 100,
        };
    }
    filters.thumbnail = {
        size: [size, size],
        mode: 'inset',
    };

    return {
        transformations: [
            {
                module: 'imagine',
                options: {
                    format,
                    filters,
                },
            },
            {
                module: 'set_dpi',
                options: {
                    dpi: sd.options['resolution'],
                },
            },
        ],
    };
}

function translateVideoSettings(sd: PhraseanetSubdefStruct): object {
    // too bad: phraseanet api does not provide the target "mediatype" (image, video, ...)
    // so we guess from the presence of option(s) "icodec", "vcodec", "acodec"
    if (sd.options['vcodec']) {
        // also have a acodec, so test first
        return translateVideoSettings_withVcodec(sd);
    }
    if (sd.options['acodec']) {
        // here no vcodec: pure audio
        return translateVideoSettings_withAcodec(sd);
    }
    if (sd.options['icodec']) {
        return translateVideoSettings_withIcodec(sd);
    }
    return {};
}

function translateVideoSettings_withVcodec(sd: PhraseanetSubdefStruct): object {
    // todo : gop
    const formatMap: Record<string, string> = {
        libvpx: 'video-webm',
        libtheora: 'video-webm',
        libx264: 'video-mpeg4',
    };
    const format = formatMap[sd.options['vcodec']] ?? '';
    if (!format) {
        throw new Error(
            `Unsupported video codec: ${sd.options['vcodec']} for subdef video: ${sd.name}`
        );
    }

    const size = sd.options['size'] ?? 100;

    const ffmpegModuleOptions: any = {
        format,
        timeout: 7200,
        filters: [
            {
                name: 'resize',
                width: size,
                height: size,
                mode: 'inset',
            },
        ],
    };
    // in phraseanet, "audiobitrate" is already in K !
    const audiokbrate = sd.options['audiobitrate'] ?? 0;
    if (audiokbrate > 0) {
        ffmpegModuleOptions['audio_kilobitrate'] = audiokbrate;
    }

    if (null !== sd.options['acodec']) {
        const audioCodecs = [
            'libfaac',
            'libvo_aacenc',
            'libmp3lame',
            'libvorbis',
            'libfdk_aac',
        ];
        if (!audioCodecs.includes(sd.options['acodec'])) {
            throw new Error(
                `Unsupported audio codec: ${sd.options['acodec']} for subdef video: ${sd.name}`
            );
        }
        ffmpegModuleOptions['audio_codec'] = sd.options['acodec'];
    }

    const audioSamplerate = sd.options['audiosamplerate'] ?? 0;
    if (audioSamplerate > 0) {
        ffmpegModuleOptions['filters'].push({
            name: 'resample_audio',
            rate: audioSamplerate,
        });
    }

    return {
        transformations: [
            {
                module: 'ffmpeg',
                options: ffmpegModuleOptions,
            },
        ],
    };
}

function translateVideoSettings_withAcodec(sd: PhraseanetSubdefStruct): object {
    return translateAudioSettings_withAcodec(sd);
}

function translateVideoSettings_withIcodec(sd: PhraseanetSubdefStruct): object {
    if (sd.options['delay'] === undefined) {
        // a static image
        return translateVideoSettings_targetImageFrame(sd);
    } else {
        // a animated gif (ignore icodec, always use gif)
        return translateVideoSettings_targetAnimatedGif(sd);
    }
}

function translateVideoSettings_targetImageFrame(
    sd: PhraseanetSubdefStruct
): object {
    const formatMap: Record<string, string> = {
        jpeg: 'image-jpeg',
        png: 'image-png',
        tiff: 'image-tiff',
    };
    const format = formatMap[sd.options['icodec']] ?? '';
    if (!format) {
        throw new Error(
            `Unsupported image codec: ${sd.options['icodec']} for subdef video: ${sd.name}`
        );
    }

    const transformations: Array<object> = (translateImageSettings(sd) as any)
        .transformations;

    transformations.unshift({
        module: 'video_to_frame',
        options: {
            format,
            start: 0,
        },
    });

    return {
        transformations,
    };
}

function translateVideoSettings_targetAnimatedGif(
    sd: PhraseanetSubdefStruct
): object {
    const size = sd.options['size'] ?? 100;
    // fps from (msec)delay, with 2 decimals
    const fps = Math.round(100000.0 / sd.options['delay']) / 100;

    return {
        transformations: [
            {
                module: 'video_to_animation',
                options: {
                    format: 'animated-gif',
                    start: 0,
                    duration: 5,
                    fps: fps,
                    width: size,
                    height: size,
                },
            },
        ],
    };
}

function translateAudioSettings(sd: PhraseanetSubdefStruct): object {
    // too bad: phraseanet api does not provide the target "mediatype" (image, video, ...)
    // so we guess from the presence of option(s) "icodec", "acodec"
    if (sd.options['acodec']) {
        // here no vcodec: pure audio
        return translateAudioSettings_withAcodec(sd);
    }
    if (sd.options['icodec']) {
        return translateAudioSettings_withIcodec(sd);
    }
    return {};
}

function translateAudioSettings_withAcodec(sd: PhraseanetSubdefStruct): object {
    const formatMap: Record<string, string> = {
        pcm_s16le: 'audio-wav',
        libmp3lame: 'audio-mp3',
        flac: 'audio-aac',
    };
    const format = formatMap[sd.options['acodec']] ?? '';
    if (!format) {
        throw new Error(
            `Unsupported audio codec: ${sd.options['acodec']} for subdef video: ${sd.name}`
        );
    }

    const ffmpegModuleOptions: Record<string, any> = {
        format,
        timeout: 7200,
    };

    // in phraseanet, "audiobitrate" is already in K !
    const audiokbrate = sd.options['audiobitrate'] ?? 0;
    if (audiokbrate > 0) {
        ffmpegModuleOptions['audio_kilobitrate'] = audiokbrate;
    }

    const audioSamplerate = sd.options['audiosamplerate'] ?? 0;
    if (audioSamplerate > 0) {
        ffmpegModuleOptions['filters'] = [
            {
                name: 'resample_audio',
                rate: audioSamplerate,
            },
        ];
    }

    return {
        transformations: [
            {
                module: 'ffmpeg',
                options: ffmpegModuleOptions,
            },
        ],
    };
}

function translateAudioSettings_withIcodec(sd: PhraseanetSubdefStruct): object {
    const icodecs = ['jpeg', 'png', 'tiff'];
    if (!icodecs.includes(sd.options['icodec'])) {
        throw new Error(
            `Unsupported image codec: ${sd.options['icodec']} for subdef video: ${sd.name}`
        );
    }

    const size = sd.options['size'] ?? 100;

    return {
        transformations: [
            {
                module: 'album_artwork',
                options: {
                    format: 'image-' + sd.options['icodec'],
                },
            },
            {
                module: 'imagine',
                options: {
                    filters: {
                        thumbnail: {
                            size: [size, size],
                        },
                    },
                },
            },
        ],
    };
}
