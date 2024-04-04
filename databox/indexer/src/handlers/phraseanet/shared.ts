import {Asset} from '../../indexers';
import {FieldMap, SubDef} from './types';
import { CPhraseanetRecord } from './CPhraseanetRecord';

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

    // @ts-ignore
    for(const [name, fm] of fieldMap) {
        const ad = fm.attributeDefinition;

        for(const v of fm.values) {
            let values;
            switch(v.type) {
                case "template":    // output : string | string[]
                    values = (await v.twig.renderAsync({record: record}))
                        .split("\n").map((p: string) => p.trim())
                        .filter((p: string) => p);
                    if(!ad.multiple) {
                        values = values.join(' ; ');
                    }
                    break;
                case "metadata":   // output : string | string[]
                    values = ad.multiple ?
                        (await record.getMetadata(v.value)).values
                        :
                        (await record.getMetadata(v.value)).value;
                    break;
                default:          // output : any
                    values = v.value;
                    break;
            }
            switch(fm.type) {
                case DataboxAttributeType.Number:
                    if(typeof values === "string") {
                        values = Number(values).toString();
                    }
                    break;
                case DataboxAttributeType.Json:
                   if(typeof values === "object") {
                       values = JSON.stringify(values);
                   }
                   break;
                // todo: better handle of mono/multi/object
            }

            const d = {
                definitionId: ad.id,
                origin: 'machine',
                originVendor: 'indexer-import',
                locale: v.locale ?? null,
                position: fm.position,
            } as Partial<AttributeInput>;

            attributes.push({
                ...d,
                value: values,
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
        shortcutIntoCollections: shortcutIntoCollections
    };
}

export enum PhraseanetSearchType {
    Record = 0,
    Story = 1,
}

export enum DataboxAttributeType {
    Boolean = "boolean",
    Code = "code",
    Color = "color",
    Date = "date",
    DateTime = "date_time",
    GeoPoint = "geo_point",
    Html = "html",
    Ip = "ip",
    Json = "json",
    Keyword = "keyword",
    Number = "number",
    Text = "text"
};

export const attributeTypesEquivalence: Record<string, DataboxAttributeType> = {
    string: DataboxAttributeType.Text,
    date: DataboxAttributeType.Date,
    number: DataboxAttributeType.Number
};

