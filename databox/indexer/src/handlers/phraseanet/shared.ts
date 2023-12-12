import {Asset} from '../../indexers';
import {PhraseanetRecord, SubDef} from './types';
import {escapeSlashes} from '../../lib/pathUtils';
import {
    AttributeClass,
    AttributeInput,
    RenditionInput,
} from '../../databox/types';

const renditionDefinitionMapping = {
    document: 'original',
};
const renditionDefinitionBlacklist = ['original'];

export type AttrDefinitionIndex = Record<
    string,
    {
        id: string;
        multiple: boolean;
    }
>;

export type AttrClassIndex = Record<string, AttributeClass>;

export function createAsset(
    workspaceId: string,
    importFiles: boolean,
    record: PhraseanetRecord,
    collectionName: string,
    attrDefinitionIndex: AttrDefinitionIndex
): Asset {
    const document: SubDef | undefined = record.subdefs.find(
        s => s.name === 'document'
    );

    const path = `${escapeSlashes(collectionName)}/${escapeSlashes(
        record.original_name
    )}`;

    return {
        workspaceId,
        key: record.uuid,
        path,
        title: record.title,
        importFile: importFiles,
        publicUrl: document?.permalink.url,
        isPrivate: false,
        attributes: record.caption?.map(c => {
            const ad = attrDefinitionIndex[c.meta_structure_id.toString()];

            const d = {
                definitionId: ad.id,
                origin: 'machine',
                originVendor: 'indexer-import',
            } as Partial<AttributeInput>;

            return {
                ...d,
                value: ad.multiple ? c.value.split(' ; ') : c.value,
            } as AttributeInput;
        }),
        generateRenditions: false,
        renditions: record.subdefs
            .map(s => {
                const defName = renditionDefinitionMapping[s.name] || s.name;

                if (renditionDefinitionBlacklist.includes(defName)) {
                    return null;
                }

                return {
                    name: defName,
                    sourceFile: {
                        url: s.permalink.url,
                        isPrivate: false,
                        importFile: importFiles,
                        type: s.mime_type,
                    },
                };
            })
            .filter(s => Boolean(s)) as RenditionInput[],
    };
}

export const attributeTypesEquivalence = {
    string: 'text',
};
