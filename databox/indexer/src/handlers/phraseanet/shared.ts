import {Asset} from "../../indexers";
import {PhraseanetRecord, SubDef} from "./types";

const renditionDefinitionMapping = {
    document: 'original',
};
const renditionDefinitionBlacklist = [
    'original',
];

export function createAsset(
    record: PhraseanetRecord,
    collectionName: string,
    attrDefinitionIndex: Record<string, string>
): Asset {
    const document: SubDef | undefined = record.subdefs.find(s => s.name === 'document');
    const path = `${collectionName}/${record.title}`;

    return {
        key: record.uuid,
        path,
        publicUrl: document.permalink.url,
        isPrivate: false,
        attributes: record.caption?.map(c => ({
            value: c.value,
            definition: `/attribute-definitions/${attrDefinitionIndex[c.meta_structure_id.toString()]}`,
            origin: 'machine',
            originVendor: 'indexer-import',
        })),
        generateRenditions: false,
        renditions: record.subdefs.map(s => {
            const defName = renditionDefinitionMapping[s.name] || s.name;

            if (renditionDefinitionBlacklist.includes(defName)) {
                return null;
            }

            return {
                definition: defName,
                source: {
                    url: s.permalink.url,
                    isPrivate: false,
                }
            };
        }).filter(s => Boolean(s)),
    };
}

export const attributeTypesEquivalence = {
    string: 'text',
};
