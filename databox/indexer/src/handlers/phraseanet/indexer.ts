import {IndexIterator} from "../../indexers";
import {ConfigDataboxMapping, PhraseanetConfig} from "./types";
import PhraseanetClient from "./phraseanetClient";
import {AttrDefinitionIndex, attributeTypesEquivalence, createAsset} from "./shared";
import {forceArray} from "../../lib/utils";
import {getStrict} from "../../configLoader";

export const phraseanetIndexer: IndexIterator<PhraseanetConfig> = async function* (
    location,
    logger,
    databoxClient
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

    for (let dm of databoxMapping) {
        await databoxClient.flushWorkspace(dm.workspaceId);

        let offset = 0;
        logger.debug(`Start indexing databox "${dm.databoxId}" to workspace "${dm.workspaceId}"`);

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
                workspace: `/workspaces/${dm.workspaceId}`,
            });
        }

        const searchParams = {
            bases: databoxCollections[dm.databoxId],
        };
        let records = await client.search(searchParams, offset);
        while (records.length > 0) {
            for (let r of records) {
                yield createAsset(r, collectionIndex[r.base_id], attrDefinitionIndex);
            }
            offset += records.length;

            records = await client.search(searchParams, offset);
        }
    }
}
