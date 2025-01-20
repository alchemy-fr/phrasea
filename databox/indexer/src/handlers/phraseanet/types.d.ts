import {AttributeDefinition} from '../../databox/types';
import Twig from 'twig';
import {DataboxAttributeType} from './shared.ts';

export type FieldMapValue = {
    locale?: string;
    type: 'template' | 'metadata' | 'text';
    value: any | any[];
    twig?: Twig.template;
};

export type FieldMap = {
    id: string;
    position: number;
    type: DataboxAttributeType;
    multivalue: boolean;
    readonly: boolean;
    translatable: boolean;
    labels: Record<string, string>;
    values: FieldMapValue[];
    attributeDefinition: AttributeDefinition;
};

export type ConfigPhraseanetSubdefBase = {
    useAsThumbnail?: boolean;
    useAsPreview?: boolean;
    useAsOriginal?: boolean;
    useAsThumbnailActive?: boolean;
    pickSourceFile?: boolean;
    class: string;
};

export type ConfigPhraseanetOriginal = ConfigPhraseanetSubdefBase & {
    from: string;
};

export type ConfigRenditionBuilder = {
    from: string;
};

export type ConfigPhraseanetSubdef = ConfigPhraseanetSubdefBase & {
    parent: string|null;
    builders: Record<string, ConfigRenditionBuilder>;
};

export type ConfigDataboxMapping = {
    databox: string;
    collections?: string;
    searchQuery?: string;
    workspaceSlug: string;
    recordsCollectionPath: string;
    copyTo: string;
    storiesCollectionPath: string;
    fieldMap: Map<string, FieldMap>;
    sourceFile?: string;
    renditions: Record<string, ConfigPhraseanetOriginal | ConfigPhraseanetSubdef> | false;
};

export type PhraseanetConfig = {
    url: string;
    instanceId?: string;
    idempotencePrefixes?: {
        asset?: string;
        collection?: string;
        attributeDefinition?: string;
        renditionDefinition?: string;
    };
    searchOrder?: string;
    token: string;
    verifySSL?: boolean;
    importFiles?: boolean;
    databoxMapping: ConfigDataboxMapping[];
};

export enum PhraseanetMetadataType {
    Date = 'date',
    Number = 'number',
    String = 'string',
}

export type PhraseanetMetaStruct = {
    id: string;
    namespace: string;
    source: string;
    tagname: string;
    name: string;
    separator: string;
    thesaurus_branch: string;
    type: PhraseanetMetadataType;
    indexable: boolean;
    multivalue: boolean;
    readonly: boolean;
    required: boolean;
    labels: Record<string, string>;
};

export type PhraseanetStatusBitStruct = {
    bit: number;
    label_on: string;
    label_off: string;
};

export type PhraseanetStatusBit = {
    bit: number;
    state: boolean;
};

export type PhraseanetSubdefStruct = {
    type: string; // image | video | audio | document
    name: string; // thumbnail, thumbnail_gif, preview, preview_webm ...
    databox_id: number;
    class: string; // thumbnail | preview (todo: check other possible values ?)
    preset: Record<string, string>;
    downloadable: boolean;
    devices: string[];
    labels: Record<string, string>;
    options: Record<string, any>;
};

export type PhraseanetSubdef = {
    name: string;
    height: number;
    width: number;
    filesize: number;
    player_type: string;
    mime_type: string;
    created_on: string;
    updated_on: string;
    url: string;
    permalink: {
        url: string;
    };
};

export type PhraseanetDatabox = {
    databox_id: string;
    name: string;
    viewname: string;
    labels: Record<string, string>;
    collections: Record<string, PhraseanetCollection>;
    baseIds: string[];
    _metaStructSet: boolean;
    metaStruct: Record<string, PhraseanetMetaStruct>;
};

export type PhraseanetCollection = {
    databox_id: string;
    base_id: string;
    collection_id: number;
    name: string;
};

type PhraseanetMetadata = {
    meta_structure_id: string;
    name: string;
    value: string;
};

export type PhraseanetRecord = {
    resource_id: string;
    databox_id: string;
    base_id: string;
    record_id: string;
    collection_id: string;
    uuid: string;
    title: string;
    original_name: string;
    mime_type: string;
    phrasea_type: string;
    type: string;
    created_on: string;
    updated_on: string;
    subdefs: PhraseanetSubdef[];
    status: PhraseanetStatusBit[];
    metadata: PhraseanetMetadata[];
};

export type PhraseanetStory = {
    resource_id: string;
    databox_id: string;
    base_id: string;
    story_id: string;
    collection_id: string;
    uuid: string;
    title: string;
    original_name: string;
    mime_type: string;
    created_on: string;
    updated_on: string;
    subdefs: PhraseanetSubdef[];
    status: PhraseanetStatusBit[];
    metadata: PhraseanetMetadata[];
    children: PhraseanetRecord[];
};
