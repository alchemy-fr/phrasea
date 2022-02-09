import {IndexIterator} from "../../indexers";
import {PhraseanetConfig, SubDef} from "./types";
import PhraseanetClient from "./phraseanetClient";
import {attributeTypesEquivalence, createAsset} from "./shared";
import {forceArray} from "../../lib/utils";

export const phraseanetIndexer: IndexIterator<PhraseanetConfig> = async function* (
    location,
    logger,
    databoxClient
) {
    const client = new PhraseanetClient(location.options);

    let offset = 0;

    const collectionIndex = {};
    const collections = await client.getCollections();
    for (let c of collections) {
        collectionIndex[c.base_id] = c.name;
    }

    const attrDefinitionIndex: Record<string, string> = {};
    const metaStructure = forceArray(await client.getMetaStruct());
    for (let m of metaStructure) {
        logger.debug(`Creating "${m.name}" attribute definition`);
        try {
            const id = m.id.toString();
            attrDefinitionIndex[id] = await databoxClient.createAttributeDefinition(m.id.toString(), {
                key: id,
                name: m.name,
                editable: !m.readonly,
                multiple: m.multivalue,
                public: true,
                fieldType: attributeTypesEquivalence[m.type] || m.type,
            });
        } catch (e) {
            if (e.response && e.response.data) {
                continue;
            }

            throw e;
        }
    }

    let records = await client.search(offset);
    while (records.length > 0) {
        for (let r of records) {
            yield createAsset(r, collectionIndex[r.base_id], attrDefinitionIndex);
        }
        offset += records.length;

        records = await client.search(offset);
    }
}
