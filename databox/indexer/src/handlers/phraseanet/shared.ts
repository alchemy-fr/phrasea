import {Asset} from '../../indexers';
import {FieldMap, SubDef} from './types';
import { CPhraseanetRecord } from './CPhraseanetRecord.ts';

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

export async function createAsset(
    workspaceId: string,
    importFiles: boolean,
    record: CPhraseanetRecord,
    path: string,
    collectionKeyPrefix: string,
    key: string,
    fieldMap: Map<string, FieldMap>,
    tagIndex: TagIndex,
    shortcutIntoCollections: string[]
): Promise<Asset> {
    const document: SubDef | undefined = record.subdefs.find(
        s => s.name === 'document'
    );

    const attributes: AttributeInput[] = [];

    let k: string, fm: FieldMap;
    for([k, fm] of fieldMap) {
        const ad = fm.attributeDefinition;

        const values = (await fm.twig.renderAsync({record: record}))
            .split("\n").map((p: string) => p.trim())
            .filter((p: string) => p);

        const d = {
            definitionId: ad.id,
            origin: 'machine',
            originVendor: 'indexer-import',
            locale: fm.locale,
        } as Partial<AttributeInput>;

        attributes.push({
            ...d,
            value: ad.multiple ? values : values.join(' ; '),
        } as AttributeInput);
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
        shortcutIntoCollections: shortcutIntoCollections
    };
}

export const attributeTypesEquivalence: Record<string, string> = {
    string: 'text',
};

export enum PhraseanetSearchType {
    Record = 0,
    Story = 1,
}
