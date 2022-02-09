import {Asset} from "../../indexers";
import {PhraseanetRecord, SubDef} from "./types";
import {escapeSlashes} from "../../lib/pathUtils";

const renditionDefinitionMapping = {
    document: 'original',
};
const renditionDefinitionBlacklist = [
    'original',
];

export type AttrDefinitionIndex = Record<string, string>;

export function createAsset(
    record: PhraseanetRecord,
    collectionName: string,
    attrDefinitionIndex: AttrDefinitionIndex
): Asset {
    const document: SubDef | undefined = record.subdefs.find(s => s.name === 'document');

    const path = `${escapeSlashes(collectionName)}/${escapeSlashes(record.original_name)}`;

    return {
        key: record.uuid,
        path,
        title: record.title,
        publicUrl: document?.permalink.url,
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
