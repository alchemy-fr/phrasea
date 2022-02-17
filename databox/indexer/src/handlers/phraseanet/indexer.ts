import {IndexIterator} from "../../indexers";
import {ConfigDataboxMapping, PhraseanetConfig} from "./types";
import PhraseanetClient from "./phraseanetClient";
import {AttrDefinitionIndex, attributeTypesEquivalence, createAsset} from "./shared";
import {forceArray} from "../../lib/utils";
import {getConfig, getStrict} from "../../configLoader";

export const phraseanetIndexer: IndexIterator<PhraseanetConfig> = async function* (
    location,
    logger,
    databoxClient,
    options
) {
    const client = new PhraseanetClient(location.options);
    const collectionIndex: Record<string, string> = {};
    const databoxCollections: Record<string, number[]> = {};
    logger.debug(`Fetching collections`);
    const collections = await client.getCollections();
    for (let c of collections) {
        collectionIndex[c.base_id] = c.name;
        const databoxId = c.databox_id.toString();
        if (!databoxCollections[databoxId]) {
            databoxCollections[databoxId] = [];
        }
        databoxCollections[databoxId].push(c.base_id);
    }

    const databoxMapping: ConfigDataboxMapping[] = getStrict('databoxMapping', location.options);
    const importFiles: boolean = getConfig('importFiles', false, location.options);

    for (let dm of databoxMapping) {
        let workspaceId = await databoxClient.getWorkspaceIdFromSlug(dm.workspaceSlug);
        if (options.createNewWorkspace) {
            logger.debug(`Flushing databox workspace "${dm.workspaceSlug}"`);
            workspaceId = await databoxClient.flushWorkspace(workspaceId);
        }

        logger.debug(`Start indexing databox "${dm.databoxId}" to workspace "${dm.workspaceSlug}"`);

        const attrDefinitionIndex: AttrDefinitionIndex = {};
        logger.debug(`Fetching Meta structures`);
        const metaStructure = forceArray(await client.getMetaStruct(dm.databoxId));
        for (let m of metaStructure) {
            logger.debug(`Creating "${m.name}" attribute definition`);
            const id = m.id.toString();
            attrDefinitionIndex[id] = await databoxClient.createAttributeDefinition(m.id.toString(), {
                key: id,
                name: m.name,
                editable: !m.readonly,
                multiple: m.multivalue,
                public: true,
                fieldType: attributeTypesEquivalence[m.type] || m.type,
                workspace: `/workspaces/${workspaceId}`,
            });
        }

        logger.debug(`Fetching subdefs`);
        const classIndex: Record<string, string> = {};
        const renditionClasses = await databoxClient.getRenditionClasses(workspaceId);
        renditionClasses.forEach(rc => {
            classIndex[rc.name] = rc.id;
        })

        const subDefs = (await client.getSubDefinitions()).filter(s => s.databox_id.toString() === dm.databoxId);
        for (let sd of subDefs) {
            if (!classIndex[sd.class]) {
                logger.debug(`Creating rendition class "${sd.class}" `);
                classIndex[sd.class] = await databoxClient.createRenditionClass({
                    name: sd.class,
                    workspace: `/workspaces/${workspaceId}`,
                });
            }

            logger.debug(`Creating rendition "${sd.name}"`);
            await databoxClient.createRenditionDefinition({
                name: sd.name,
                class: `/rendition-classes/${classIndex[sd.class]}`,
                useAsOriginal: sd.name === 'document',
                useAsPreview: sd.name === 'preview',
                useAsThumbnail: sd.name === 'thumbnail',
                useAsThumbnailActive: sd.name === 'thumbnailgif',
                priority: 0,
                workspace: `/workspaces/${workspaceId}`,
            });
        }

        const searchParams = {
            bases: databoxCollections[dm.databoxId],
        };
        let offset = 0;
        let records = await client.search(searchParams, offset);
        while (records.length > 0) {
            offset += records.length;
            const nextSearchPromise = client.search(searchParams, offset);
            for (let r of records) {
                yield createAsset(workspaceId, importFiles, r, collectionIndex[r.base_id], attrDefinitionIndex);
            }

            records = await nextSearchPromise;
        }
    }
}
