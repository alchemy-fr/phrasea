import {Asset} from '../../indexers';
import {FieldMap} from './types';
import {CPhraseanetRecord, CPhraseanetStory} from './CPhraseanetRecord';
import {Logger} from 'winston';
import {AttributePolicy, AttributeInput} from '../../databox/types';

export type AttrDefinitionIndex = Record<
    string,
    {
        id: string;
        multiple: boolean;
    }
>;

export type TagIndex = Record<number, string>;

export type AttrPolicyIndex = Record<string, AttributePolicy>;

/**
 * Extract the source record for story renditions using priority logic:
 * 1. If cover_record_id exists → return that record
 * 2. Else if children exist → return first child
 * 3. Else → return undefined (empty story)
 */
export function getStorySourceRecord(
    story: CPhraseanetStory
): CPhraseanetRecord | undefined {
    // Priority 1: cover_record_id
    if (story.cover_record_id !== null && story.cover_record_id !== undefined) {
        // Try to find this record in children if available
        if (story.children && story.children.length > 0) {
            const coverRecord = story.children.find(
                c => c.record_id === String(story.cover_record_id)
            );
            if (coverRecord) {
                return coverRecord;
            }
        }
        // Note: If cover_record_id not found in children array, we would need
        // an additional API call. For now, log warning and fall through to Priority 2.
    }

    // Priority 2: First child
    if (story.children && story.children.length > 0) {
        return story.children[0];
    }

    // No source record available
    return undefined;
}

/**
 * Extract renditions from a source record using the subdef-to-rendition mapping.
 * Returns array of renditions configured for this record type.
 */
export function extractRenditionsFromRecord(
    sourceRecord: CPhraseanetRecord | CPhraseanetStory,
    subdefToRendition: Record<string, string[]>,
    importFiles: boolean,
    logger: Logger
): Array<{
    name: string;
    sourceFile: {
        url: string;
        isPrivate: boolean;
        importFile: boolean;
        type: string;
    };
}> {
    const renditions = [];

    for (const sd of sourceRecord.subdefs ?? []) {
        const phrName = sourceRecord.phrasea_type + ':' + sd.name;

        for (const name of subdefToRendition[phrName] ?? []) {
            logger.info(
                `  story rendition "${name}": (from "${sd.name}"): ${sd.permalink.url}`
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

    return renditions;
}

/**
 * Extract renditions from embeds (V1 API response) using the subdef-to-rendition mapping.
 * Used for story cover_record_id embeds extraction.
 */
export function extractRenditionsFromEmbeds(
    embeds: Array<{
        name: string;
        permalink: { url: string };
        mime_type: string;
    }>,
    phrasea_type: string,
    subdefToRendition: Record<string, string[]>,
    importFiles: boolean,
    logger: Logger
): Array<{
    name: string;
    sourceFile: {
        url: string;
        isPrivate: boolean;
        importFile: boolean;
        type: string;
    };
}> {
    const renditions = [];

    for (const embed of embeds) {
        const phrName = phrasea_type + ':' + embed.name;

        for (const name of subdefToRendition[phrName] ?? []) {
            logger.info(
                `  story rendition "${name}": (from cover_record embed "${embed.name}"): ${embed.permalink.url}`
            );
            renditions.push({
                name: name,
                sourceFile: {
                    url: embed.permalink.url,
                    isPrivate: false,
                    importFile: importFiles,
                    type: embed.mime_type,
                },
            });
        }
    }

    return renditions;
}

export async function createAsset(
    workspaceId: string,
    importFiles: boolean,
    record: CPhraseanetRecord | CPhraseanetStory,
    path: string,
    collectionKeyPrefix: string,
    key: string,
    isStory: boolean,
    fieldMap: Record<string, FieldMap>,
    tagIndex: TagIndex,
    shortcutIntoCollections: {id: string; path: string}[],
    sourceSubdefName: string | undefined,
    subdefToRendition: Record<string, string[]>,
    logger: Logger
): Promise<Asset> {
    const attributes: AttributeInput[] = [];

    // Handle special system fields
    if (fieldMap['phr_record_id']) {
        const idValue = record instanceof CPhraseanetStory 
            ? record.story_id 
            : record.record_id;
        const fm = fieldMap['phr_record_id'];
        const d = {
            definition: fm.attributeDefinition.id,
            origin: 'machine',
            originVendor: 'indexer-import',
            locale: '',
            position: fm.position ?? 0,
        } as Partial<AttributeInput>;
        attributes.push({
            ...d,
            value: idValue,
        } as AttributeInput);
    }

    if (fieldMap['phr_created_on']) {
        const fm = fieldMap['phr_created_on'];
        const d = {
            definition: fm.attributeDefinition.id,
            origin: 'machine',
            originVendor: 'indexer-import',
            locale: '',
            position: fm.position ?? 0,
        } as Partial<AttributeInput>;
        attributes.push({
            ...d,
            value: record.created_on,
        } as AttributeInput);
    }

    for (const name in fieldMap) {
        // Skip system fields as they're already handled above
        if (name === 'phr_record_id' || name === 'phr_created_on') {
            continue;
        }

        const fm = fieldMap[name];
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
        name: record.title,
        importFile: importFiles,
        publicUrl: sourceFileUrl,
        isPrivate: false,
        attributes: attributes,
        tags: tags,
        generateRenditions: false,
        renditions: renditions,
        shortcutIntoCollections: shortcutIntoCollections,
        isStory: isStory,
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
