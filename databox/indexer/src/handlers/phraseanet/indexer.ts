import {IndexIterator} from "../../indexers";
import {ConfigDataboxMapping, PhraseanetConfig, SubDef} from "./types";
import PhraseanetClient from "./phraseanetClient";
import {attributeTypesEquivalence, createAsset} from "./shared";
import {forceArray} from "../../lib/utils";
import {getStrict} from "../../configLoader";

export const phraseanetIndexer: IndexIterator<PhraseanetConfig> = async function* (
    location,
    logger,
    databoxClient
) {
    const client = new PhraseanetClient(location.options);

    let offset = 0;

    const collectionIndex = {};
    logger.debug(`Fetching collections`);
    const collections = await client.getCollections();
    for (let c of collections) {
        collectionIndex[c.base_id] = c.name;
    }

    const databoxMapping: ConfigDataboxMapping[] = getStrict('databoxMapping', location.options);

    for (let dm of databoxMapping) {
        logger.debug(`Starting databox "${dm.databoxId}" to workspace "${dm.workspaceId}"`);
        const attrDefinitionIndex: Record<string, string> = {};
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

        let records = await client.search(offset);
        while (records.length > 0) {
            for (let r of records) {
                console.log('=> Phraseanet record', r);
                yield createAsset(r, collectionIndex[r.base_id], attrDefinitionIndex);
            }
            offset += records.length;

            records = await client.search(offset);
        }

    }
}
