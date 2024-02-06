import {IndexIterator} from '../../indexers';
import {ConfigDataboxMapping, PhraseanetConfig, PhraseanetRecord, PhraseanetStory} from './types';
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
        const collectionIndex: Record<string, string> = {};
        const databoxCollections: Record<string, number[]> = {};

        const instanceId = await client.getInstanceId();

        logger.debug(`Fetching collections`);
        const collections = await client.getCollections();
        for (const c of collections) {
            collectionIndex[c.base_id] = c.name;
            const databoxId = c.databox_id.toString();
            if (!databoxCollections[databoxId]) {
                databoxCollections[databoxId] = [];
            }
            databoxCollections[databoxId].push(c.base_id);
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

        for (const dm of databoxMapping) {

            let workspaceId = await databoxClient.getOrCreateWorkspaceIdWithSlug(
                dm.workspaceSlug
            );

            if (options.createNewWorkspace) {
                logger.debug(
                    `Flushing databox workspace "${dm.workspaceSlug}"`
                );
                workspaceId = await databoxClient.flushWorkspace(workspaceId);
            }

            logger.debug(
                `Start indexing databox "${dm.databoxId}" to workspace "${dm.workspaceSlug}"`
            );

            // prefix of the idempotence key for the __collections__
            const collectionKeyPrefix = instanceId + "_" + dm.databoxId + ":"

            // create the collection(s) for __records__
            const branch = splitPath(dm.recordsCollectionPath);
            await databoxClient.createCollectionTreeBranch(
                workspaceId,
                collectionKeyPrefix,
                branch.map(k => ({
                    key: k,
                    title: k,
                }))
            );
            logger.info(`Created records collection : "${branch.join('/')}"`)

            // create the collection(s) for __stories__
            let storiesCollectionId: string | null = null;
            if (dm.storiesCollectionPath !== null) {
                const branch = splitPath(dm.storiesCollectionPath);
                storiesCollectionId = await databoxClient.createCollectionTreeBranch(
                    workspaceId,
                    collectionKeyPrefix,
                    branch.map(k => ({
                        key: k,
                        title: k,
                    }))
                );
                logger.info(`Created stories collection : "${branch.join('/')}"`)
            }

            const attrClassIndex: AttrClassIndex = {};
            const defaultPublicClass = 'public';
            const attrDefinitionIndex: AttrDefinitionIndex = {};
            const tagIndex: TagIndex = {};

            logger.info(`Fetching Meta structures`);
            const metaStructure = forceArray(
                await client.getMetaStruct(dm.databoxId)
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
                            key: `${m.name}_${m.type}_${m.multivalue ? '1' : '0'}`,
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
            // fetch known tags
            const tagsIdByName: Record<string, string> = {};
            for(const t of await databoxClient.getTags(workspaceId)) {
                tagsIdByName[t.name] = t.id;
            }
            for(const sb of await client.getStatusBitsStruct(dm.databoxId)) {
                if(tagsIdByName[sb.label_on] === undefined) {
                    const key = instanceId + "_" + dm.databoxId + ".sb" + sb.bit;
                    const tag: Tag = await databoxClient.createTag(
                        key,
                        {
                            workspace: `/workspaces/${workspaceId}`,
                            name: sb.label_on
                        }
                    );
                    logger.info(`Created "${tag.name}" tag with key "${key}" --> id=${tag.id}`);
                    tagsIdByName[sb.label_on] = tag.id;
                }
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

            const subDefs = await client.getSubDefinitions(dm.databoxId);
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
                    key: `${sd.name}_${sd.type ?? ''}`,
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
                bases: databoxCollections[dm.databoxId],
            };


            // create stories as collections
            //
            logger.info(`Fetching stories`);
            const recordStories: Record<string, string[]> = {};   // key: record_id ; values: story_id's
            if(storiesCollectionId !== null) {
                let stories: PhraseanetStory[] = [];
                let offset = 0;
                do {
                    stories = await client.searchStories(searchParams, offset, "");
                    for (const s of stories) {
                        const storyCollId = await databoxClient.createCollection(
                            s.resource_id,
                            {
                                workspaceId: workspaceId,
                                key: s.resource_id,
                                title: s.title,
                                parent: "/collections/" + storiesCollectionId
                            }
                        );
                        logger.info(
                            `Phraseanet story "${s.title}" (#${
                                s.story_id
                            }) from base "${collectionIndex[s.base_id]}" (#${
                                s.base_id
                            }) ==> collection ${storyCollId}`
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

            // create records as assets
            //
            let records: PhraseanetRecord[];
            let offset = 0;
            do {
                records = await client.searchRecords(searchParams, offset, dm.searchQuery ?? "");
                for (const r of records) {
                    logger.info(
                        `Phraseanet asset "${r.title}" (#${
                            r.record_id
                        }) from base "${collectionIndex[r.base_id]}" (#${
                            r.base_id
                        })`
                    );
                    yield createAsset(
                        workspaceId,
                        importFiles,
                        r,
                        dm.recordsCollectionPath,
                        collectionKeyPrefix,
                        collectionIndex[r.base_id],
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
