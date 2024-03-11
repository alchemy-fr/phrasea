import {Asset} from '../../indexers';
import {PhraseanetRecord, SubDef} from './types';
import {escapeSlashes} from '../../lib/pathUtils';
import {
    AttributeClass,
    AttributeInput,
    RenditionInput,
} from '../../databox/types';

const renditionDefinitionMapping: Record<string, string> = {
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

export type TagIndex = Record<number, string>;

export type AttrClassIndex = Record<string, AttributeClass>;

export function createAsset(
    workspaceId: string,
    importFiles: boolean,
    record: PhraseanetRecord,
    rootCollectionPath: string,
    collectionKeyPrefix: string,
    key: string,
    collectionName: string,
    attrDefinitionIndex: AttrDefinitionIndex,
    tagIndex: TagIndex,
    storyCollectionIds: string[]
): Asset {
    const document: SubDef | undefined = record.subdefs.find(
        s => s.name === 'document'
    );

    const path = `${rootCollectionPath}/${escapeSlashes(
        collectionName
    )}/${escapeSlashes(record.original_name)}`;

    const attributes: AttributeInput[] = [];
    for (const c of record.caption ?? []) {
        const ad = attrDefinitionIndex[c.meta_structure_id.toString()];
        if (ad !== undefined) {
            const d = {
                definitionId: ad.id,
                origin: 'machine',
                originVendor: 'indexer-import',
            } as Partial<AttributeInput>;

            attributes.push({
                ...d,
                value: ad.multiple ? c.value.split(' ; ') : c.value,
            } as AttributeInput);
        }
    }

    const tags: string[] = [];
    for (const sb of record.status) {
        if (sb.state && tagIndex[sb.bit] !== undefined) {
            tags.push(tagIndex[sb.bit]);
        }
    }

    return {
        workspaceId: workspaceId,
        key: key,
        path: path,
        collectionKeyPrefix: collectionKeyPrefix,
        title: record.title,
        importFile: importFiles,
        publicUrl: document?.permalink.url,
        isPrivate: false,
        attributes: attributes,
        tags: tags,
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
        shortcutIntoCollections: storyCollectionIds,
    };
}

export const attributeTypesEquivalence: Record<string, string> = {
    string: 'text',
};

export enum PhraseanetSearchType {
    Record = 0,
    Story = 1,
}
