import {IndexIterator} from '../../indexers';
import {
    ConfigDataboxMapping,
    PhraseanetConfig,
    PhraseanetDatabox,
    PhraseanetRecord,
    PhraseanetStory
} from './types';
import PhraseanetClient from './phraseanetClient';
import {
    AttrClassIndex,
    AttrDefinitionIndex,
    attributeTypesEquivalence,
    createAsset,
    TagIndex,
} from './shared';
import {forceArray} from '../../lib/utils';
import {getConfig, getStrict} from '../../configLoader';
import {splitPath} from "../../lib/pathUtils";
import {Tag} from "../../databox/types";

export const phraseanetIndexer: IndexIterator<PhraseanetConfig> =
    async function* (location, logger, databoxClient, options) {
        const client = new PhraseanetClient(location.options);
        const databoxIndex: Record<string, PhraseanetDatabox> = {};


        logger.info(`Fetching databoxes and collections`);
        for(const db of await client.getDataboxes()) {
            db.collections = {};
            db.baseIds = [];
            databoxIndex[db.name] = databoxIndex[db.databox_id.toString()] = db;
        }
        for(const c of await client.getCollections()) {
            databoxIndex[c.databox_id.toString()].collections[c.base_id.toString()] =
                databoxIndex[c.databox_id.toString()].collections[c.name] = c;
            databoxIndex[c.databox_id.toString()].baseIds.push(c.base_id.toString());
        }

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
        for(const k of ["asset", "collection", "attributeDefinition", "renditionDefinition"]) {
            idempotencePrefixes[k] = getConfig(
                `idempotencePrefixes.${k}`,
                client.getId() + "_",
                location.options
            );
        }

        for (const dm of databoxMapping) {

            const databox = databoxIndex[dm.databox];
            if(databox === undefined) {
                logger.info(`Unknown databox "${dm.databox}" (ignored)`);
                continue;
            }

            let workspaceId = await databoxClient.getOrCreateWorkspaceIdWithSlug(
                dm.workspaceSlug
            );

            if (options.createNewWorkspace) {
                logger.info(
                    `Flushing databox workspace "${dm.workspaceSlug}"`
                );
                workspaceId = await databoxClient.flushWorkspace(workspaceId);
            }

            logger.info(
                `Start indexing databox "${databox.name}" (#${databox.databox_id}) to workspace "${dm.workspaceSlug}"`
            );

            const sourceCollections: string[] = [];
            if(dm.collections !== undefined) {
                for (const c of dm.collections.split(',')) {
                    const collection = databox.collections[c.trim()];
                    if (collection == undefined) {
                        logger.info(`Unknown collection "${c.trim()}" into databox "${databox.name}" (#${databox.databox_id}) (ignored)`);
                        continue;
                    }
                    sourceCollections.push(collection.base_id.toString());
                }
            }
            if(sourceCollections.length === 0) {
                for(const baseId of databox.baseIds) {
                    sourceCollections.push(baseId);
                }
            }

            const collectionKeyPrefix = idempotencePrefixes["collection"] + databox.databox_id.toString() + ":"

            const branch = splitPath(dm.recordsCollectionPath ?? "");
            await databoxClient.createCollectionTreeBranch(
                workspaceId,
                collectionKeyPrefix,
                branch.map(k => ({
                    key: k,
                    title: k,
                }))
            );
            logger.info(`Created records collection: "${branch.join('/')}"`)

            let storiesCollectionId: string | null = null;
            if (dm.storiesCollectionPath !== undefined) {
                const branch = splitPath(dm.storiesCollectionPath);
                storiesCollectionId = await databoxClient.createCollectionTreeBranch(
                    workspaceId,
                    collectionKeyPrefix,
                    branch.map(k => ({
                        key: k,
                        title: k,
                    }))
                );
                logger.info(`Created stories collection: "${branch.join('/')}"`)
            }

            const attrClassIndex: AttrClassIndex = {};
            const defaultPublicClass = 'public';
            const attrDefinitionIndex: AttrDefinitionIndex = {};
            const tagIndex: TagIndex = {};

            logger.info(`Fetching Meta structures`);
            const metaStructure = forceArray(
                await client.getMetaStruct(databox.databox_id)
            );
            for (const m of metaStructure) {
                logger.info(`Creating "${m.name}" attribute definition`);
                const id = m.id.toString();

                if (!attrClassIndex[defaultPublicClass]) {
                    const name = 'Phraseanet Public';
                    logger.info(`Creating "${name}" attribute class`);
                    attrClassIndex[defaultPublicClass] =
                        await databoxClient.createAttributeClass(
                            defaultPublicClass,
                            {
                                name,
                                public: true,
                                editable: true,
                                workspace: `/workspaces/${workspaceId}`,
                                key: defaultPublicClass,
                            }
                        );
                }

                attrDefinitionIndex[id] =
                    await databoxClient.createAttributeDefinition(
                        m.id.toString(),
                        {
                            key: `${idempotencePrefixes["attributeDefinition"]}${m.name}_${m.type}_${m.multivalue ? '1' : '0'}`,
                            name: m.name,
                            editable: !m.readonly,
                            multiple: m.multivalue,
                            fieldType:
                                attributeTypesEquivalence[m.type] || m.type,
                            workspace: `/workspaces/${workspaceId}`,
                            class: attrClassIndex[defaultPublicClass]['@id'],
                            labels: {
                                phraseanetDefinition: m,
                            },
                        }
                    );
            }

            logger.info(`Fetching status-bits`);
            const tagsIdByName: Record<string, string> = {};
            for(const sb of await client.getStatusBitsStruct(databox.databox_id)) {
                logger.info(`Creating "${sb.label_on}" tag`);
                const key = client.getId() + "_" + databox.databox_id.toString() + ".sb" + sb.bit;
                const tag: Tag = await databoxClient.createTag(
                    key,
                    {
                        workspace: `/workspaces/${workspaceId}`,
                        name: sb.label_on
                    }
                );
                tagsIdByName[sb.label_on] = tag.id;
                tagIndex[sb.bit] = "/tags/" + tagsIdByName[sb.label_on];
            }

            logger.info(`Fetching subdefs`);
            const classIndex: Record<string, string> = {};
            const renditionClasses = await databoxClient.getRenditionClasses(
                workspaceId
            );
            renditionClasses.forEach(rc => {
                classIndex[rc.name] = rc.id;
            });

            const subDefs = await client.getSubDefinitions(databox.databox_id);
            for (const sd of subDefs) {
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
                    key: `${idempotencePrefixes["renditionDefinition"]}${sd.name}_${sd.type ?? ''}`,
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

            const searchParams = {
                bases: sourceCollections,     // if empty (no collections on config) : search all collections
            };
            console.log("-------------------------- ", searchParams);

            logger.info(`Fetching stories`);
            const recordStories: Record<string, string[]> = {};   // key: record_id ; values: story_id's
            if(storiesCollectionId !== null) {
                let stories: PhraseanetStory[] = [];
                let offset = 0;
                do {
                    stories = await client.searchStories(searchParams, offset, "");
                    for (const s of stories) {

                        if(databox.collections[s.base_id] === undefined) {
                            logger.info(`=================== ${s.base_id} ==================`);
                            console.log(s);
                        //    continue;
                            // console.log(databox.collections);
                        }

                        const storyCollId = await databoxClient.createCollection(
                            s.resource_id,
                            {
                                workspaceId: workspaceId,
                                key: idempotencePrefixes["collection"] + s.databox_id + "_" + s.story_id,
                                title: s.title,
                                parent: "/collections/" + storiesCollectionId
                            }
                        );
                        logger.info(
                            `Phraseanet story "${s.title}" (#${
                                s.story_id
                            }) from base "${databox.collections[s.base_id].name}" (#${
                                s.base_id
                            }) ==> collection (#${storyCollId})`
                        );
                        for (const r of s.children) {
                            if (recordStories[r.record_id] === undefined) {
                                recordStories[r.record_id] = [];
                            }
                            recordStories[r.record_id].push(storyCollId);
                        }
                    }
                    offset += stories.length;
                }
                while (stories.length > 0);
            }

            let records: PhraseanetRecord[];
            let offset = 0;
            do {
                records = await client.searchRecords(searchParams, offset, dm.searchQuery ?? "");
                for (const r of records) {
                    logger.info(
                        `Phraseanet record "${r.title}" (#${
                            r.record_id
                        }) from base "${databox.collections[r.base_id].name}" (#${
                            r.base_id
                        })`
                    );
                    yield createAsset(
                        workspaceId,
                        importFiles,
                        r,
                        dm.recordsCollectionPath ?? "",
                        collectionKeyPrefix,
                        idempotencePrefixes["asset"] + r.databox_id + "_" + r.record_id,
                        databox.collections[r.base_id].name,
                        attrDefinitionIndex,
                        tagIndex,
                        recordStories[r.record_id] ?? []
                    );
                }
                offset += records.length;
            }
            while (records.length > 0);

        }
    };
