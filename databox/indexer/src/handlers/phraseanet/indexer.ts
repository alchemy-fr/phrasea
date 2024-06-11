import {IndexIterator} from '../../indexers';
import {ConfigDataboxMapping, FieldMap, PhraseanetConfig} from './types';
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
import {escapeSlashes, splitPath} from '../../lib/pathUtils';
import {AttributeDefinition, Tag} from '../../databox/types';
import Twig from 'twig';

export const phraseanetIndexer: IndexIterator<PhraseanetConfig> =
    async function* (location, logger, databoxClient, options) {
        Twig.extendFilter('escapePath', function (v: string) {
            return v.replace('/', '_');
        });

        const client = new PhraseanetClient(location.options, logger);

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
                client.getId() + '_',
                location.options
            );
        }

        for (const dm of databoxMapping) {
            const databox = await client.getDatabox(dm.databox);
            if (databox === undefined) {
                logger.info(`Unknown databox "${dm.databox}" (ignored)`);
                continue;
            }

            logger.info(
                `Start indexing databox "${databox.name}" (#${databox.databox_id}) to workspace "${dm.workspaceSlug}"`
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

            logger.info(`Fetching Meta structures`);
            const metaStructure = await client.getMetaStruct(
                databox.databox_id
            );
            if (!dm.fieldMap) {
                // import all fields from structure
                for (const name in metaStructure) {
                    fieldMap.set(name, {
                        id: metaStructure[name].id,
                        position: 0,
                        type:
                            attributeTypesEquivalence[
                                metaStructure[name].type
                            ] ?? DataboxAttributeType.Text,
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
            const attributeDefinitionIndex: Record<
                string,
                AttributeDefinition
            > = {};
            let ufid = 0; // used to generate a unique id for fields declared in conf, but not existing in phraseanet
            let position = 1;
            for (const [name, fm] of fieldMap) {
                fm.id = metaStructure[name]
                    ? metaStructure[name].id
                    : (--ufid).toString();
                fm.position = position++;
                fm.multivalue =
                    fm.multivalue ??
                    (metaStructure[name]
                        ? metaStructure[name].multivalue
                        : false);
                fm.readonly =
                    fm.readonly ??
                    (metaStructure[name]
                        ? metaStructure[name].readonly
                        : false);
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
                        key: `${
                            idempotencePrefixes['attributeDefinition']
                        }_${name}_${fm.type}_${fm.multivalue ? '1' : '0'}`,
                        name: name,
                        position: fm.position,
                        editable: !fm.readonly,
                        multiple: fm.multivalue,
                        fieldType:
                            attributeTypesEquivalence[fm.type ?? ''] || fm.type,
                        workspace: `/workspaces/${workspaceId}`,
                        class: attrClassIndex[defaultPublicClass]['@id'],
                        labels: fm.labels,
                        translatable: fm.translatable,
                    };
                    logger.info(`Creating "${name}" attribute definition`);
                    attributeDefinitionIndex[name] =
                        await databoxClient.createAttributeDefinition(
                            fm.id,
                            data
                        );
                }
                fm.attributeDefinition = attributeDefinitionIndex[name];
            }

            logger.info(`Fetching status-bits`);
            const tagIndex: TagIndex = {};
            const tagsIdByName: Record<string, string> = {};
            for (const sb of await client.getStatusBitsStruct(
                databox.databox_id
            )) {
                logger.info(`Creating "${sb.label_on}" tag`);
                const key =
                    client.getId() +
                    '_' +
                    databox.databox_id.toString() +
                    '.sb' +
                    sb.bit;
                const tag: Tag = await databoxClient.createTag(key, {
                    workspace: `/workspaces/${workspaceId}`,
                    name: sb.label_on,
                });
                tagsIdByName[sb.label_on] = tag.id;
                tagIndex[sb.bit] = '/tags/' + tagsIdByName[sb.label_on];
            }

            logger.info(`Fetching subdefs`);
            const classIndex: Record<string, string> = {};
            const renditionClasses =
                await databoxClient.getRenditionClasses(workspaceId);
            renditionClasses.forEach(rc => {
                classIndex[rc.name] = rc.id;
            });

            const subdefs = await client.getSubdefsStruct(databox.databox_id);
            for (const sd of subdefs) {
                if (!classIndex[sd.class]) {
                    logger.info(`Creating rendition class "${sd.class}" `);
                    classIndex[sd.class] =
                        await databoxClient.createRenditionClass({
                            name: sd.class,
                            workspace: `/workspaces/${workspaceId}`,
                        });
                }

                logger.info(
                    `Creating rendition "${sd.name}" of class "${sd.class}" for type="${sd.type}"`
                );
                await databoxClient.createRenditionDefinition({
                    name: sd.name,
                    key: `${idempotencePrefixes['renditionDefinition']}${
                        sd.name
                    }_${sd.type ?? ''}`,
                    class: `/rendition-classes/${classIndex[sd.class]}`,
                    useAsOriginal: sd.name === 'document',
                    useAsPreview: sd.name === 'preview',
                    useAsThumbnail: sd.name === 'thumbnail',
                    useAsThumbnailActive: sd.name === 'thumbnailgif',
                    priority: 0,
                    workspace: `/workspaces/${workspaceId}`,
                    labels: {
                        phraseanetDefinition: sd,
                    },
                });
            }

            const sourceCollections: string[] = [];
            if (dm.collections) {
                for (const c of dm.collections.split(',')) {
                    const collection = databox.collections[c.trim()];
                    if (collection == undefined) {
                        logger.info(
                            `Unknown collection "${c.trim()}" into databox "${
                                databox.name
                            }" (#${databox.databox_id}) (ignored)`
                        );
                        continue;
                    }
                    sourceCollections.push(collection.base_id.toString());
                }
                if (sourceCollections.length === 0) {
                    logger.info(
                        `No collection found for "${dm.collections}" into databox "${databox.name}" (#${databox.databox_id}) (databox ignored)`
                    );
                }
            } else {
                for (const baseId of databox.baseIds) {
                    sourceCollections.push(baseId);
                }
            }

            const collectionKeyPrefix =
                idempotencePrefixes['collection'] +
                databox.databox_id.toString() +
                ':';

            const branch = splitPath(dm.recordsCollectionPath ?? '');
            await databoxClient.createCollectionTreeBranch(
                workspaceId,
                collectionKeyPrefix,
                branch.map(k => ({
                    key: k,
                    title: k,
                }))
            );
            logger.info(`Created records collection: "${branch.join('/')}"`);

            let storiesCollectionId: string | null = null;
            if (dm.storiesCollectionPath !== undefined) {
                const branch = splitPath(dm.storiesCollectionPath);
                storiesCollectionId =
                    await databoxClient.createCollectionTreeBranch(
                        workspaceId,
                        collectionKeyPrefix,
                        branch.map(k => ({
                            key: k,
                            title: k,
                        }))
                    );
                logger.info(
                    `Created stories collection: "${branch.join('/')}"`
                );
            }

            const searchParams = {
                bases: sourceCollections, // if empty (no collections on config) : search all collections
            };

            const recordStories: Record<string, {id: string; path: string}[]> =
                {}; // key: record_id ; values: story_id's
            if (storiesCollectionId !== null) {
                logger.info(`Fetching stories`);
                let stories: CPhraseanetStory[] = [];
                let offset = 0;
                do {
                    stories = await client.searchStories(
                        searchParams,
                        offset,
                        ''
                    );
                    for (const s of stories) {
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
                                    parent:
                                        '/collections/' + storiesCollectionId,
                                }
                            );
                        logger.info(
                            `Phraseanet story "${s.title}" (#${
                                s.story_id
                            }) from base "${
                                databox.collections[s.base_id].name
                            }" (#${s.base_id}) ==> collection (#${storyCollId})`
                        );
                        for (const rs of s.children) {
                            if (recordStories[rs.record_id] === undefined) {
                                recordStories[rs.record_id] = [];
                            }
                            recordStories[rs.record_id].push({
                                id: storyCollId,
                                path: s.title,
                            });
                        }
                    }
                    offset += stories.length;
                } while (stories.length > 0);
            }

            logger.info(`Fetching records`);
            let records: CPhraseanetRecord[];
            let offset = 0;
            do {
                records = await client.searchRecords(
                    searchParams,
                    offset,
                    dm.searchQuery ?? ''
                );
                for (const r of records) {
                    logger.info(
                        `Phraseanet record "${r.title}" (#${
                            r.record_id
                        }) from base "${
                            databox.collections[r.base_id].name
                        }" (#${r.base_id})`
                    );

                    const copyTo = recordStories[r.record_id] ?? [];

                    // copy the asset to other location(s) ?
                    for (const ct of dm.copyTo ?? []) {
                        const template = Twig.twig({data: ct});
                        const paths = (await template.renderAsync({record: r}))
                            .split('\n')
                            .map(p => p.trim())
                            .filter(p => p);

                        for (const path of paths) {
                            const branch = splitPath(path);
                            copyTo.push({
                                path: path,
                                id: await databoxClient.createCollectionTreeBranch(
                                    workspaceId,
                                    collectionKeyPrefix,
                                    branch.map(k => ({
                                        key: k,
                                        title: k,
                                    }))
                                ),
                            });
                        }
                    }

                    const path = `${
                        dm.recordsCollectionPath ?? ''
                    }/${escapeSlashes(
                        databox.collections[r.base_id].name
                    )}/${escapeSlashes(r.original_name)}`;
                    yield createAsset(
                        workspaceId,
                        importFiles,
                        r,
                        path,
                        collectionKeyPrefix,
                        idempotencePrefixes['asset'] +
                            r.databox_id +
                            '_' +
                            r.record_id,
                        fieldMap,
                        tagIndex,
                        copyTo
                    );
                }
                offset += records.length;
            } while (records.length > 0);
        }
    };
