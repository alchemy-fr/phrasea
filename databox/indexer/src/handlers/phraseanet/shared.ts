import {Asset} from '../../indexers';
import {FieldMap} from './types';
import {CPhraseanetRecord} from './CPhraseanetRecord';
import {Logger} from 'winston';
import {AttributeClass, AttributeInput} from '../../databox/types';

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
    storyCollection: string | null,
    fieldMap: Map<string, FieldMap>,
    tagIndex: TagIndex,
    shortcutIntoCollections: {id: string; path: string}[],
    sourceSubdefName: string | undefined,
    subdefToRendition: Record<string, string[]>,
    logger: Logger
): Promise<Asset> {
    const attributes: AttributeInput[] = [];

    for (const [_name, fm] of fieldMap) {
        const ad = fm.attributeDefinition;

        for (const v of fm.values) {
            let values;
            switch (v.type) {
                case 'template': // output : string | string[]
                    values = (await v.twig.renderAsync({record: record}))
                        .split('\n')
                        .map((p: string) => p.trim())
                        .filter((p: string) => p);
                    if (!ad.multiple) {
                        values = values.join(' ; ');
                    }
                    break;
                case 'metadata': // output : string | string[]
                    values = ad.multiple
                        ? (await record.getMetadata(v.value)).values
                        : (await record.getMetadata(v.value)).value;
                    break;
                default: // output : any
                    values = v.value;
                    break;
            }
            switch (fm.type) {
                case DataboxAttributeType.Number:
                    if (typeof values === 'string') {
                        values = Number(values).toString();
                    }
                    break;
                case DataboxAttributeType.Json:
                    if (typeof values === 'object') {
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

    const renditions = [];
    let sourceFileUrl: string | undefined = undefined;

    for (const sd of record.subdefs ?? []) {
        if (sd.name === sourceSubdefName) {
            sourceFileUrl = sd.permalink.url;
            logger.info(`  source: (from "${sd.name}"): ${sd.permalink.url}`);
        }

        const phrName = record.phrasea_type + ':' + sd.name;

        for (const name of subdefToRendition[phrName] ?? []) {
            logger.info(
                `  rendition "${name}": (from "${sd.name}"): ${sd.permalink.url}`
            );
            renditions.push({
                name: name,
                sourceFile: {
                    url: sd.permalink.url,
                    isPrivate: false,
                    importFile: importFiles,
                    type: sd.mime_type,
                },
            });
        }
    }
    return {
        workspaceId: workspaceId,
        key: key,
        path: path,
        collectionKeyPrefix: collectionKeyPrefix,
        title: record.title,
        importFile: importFiles,
        publicUrl: sourceFileUrl,
        isPrivate: false,
        attributes: attributes,
        tags: tags,
        generateRenditions: false,
        renditions: renditions,
        shortcutIntoCollections: shortcutIntoCollections,
        storyCollection: storyCollection,
    };
}

export enum PhraseanetSearchType {
    Record = 0,
    Story = 1,
}

export enum DataboxAttributeType {
    Boolean = 'boolean',
    Code = 'code',
    Color = 'color',
    Date = 'date',
    DateTime = 'date_time',
    GeoPoint = 'geo_point',
    Html = 'html',
    Ip = 'ip',
    Json = 'json',
    Keyword = 'keyword',
    Number = 'number',
    Text = 'text',
}

export const attributeTypesEquivalence: Record<string, DataboxAttributeType> = {
    string: DataboxAttributeType.Text,
    date: DataboxAttributeType.Date,
    number: DataboxAttributeType.Number,
};
