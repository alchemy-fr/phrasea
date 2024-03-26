import {AttributeDefinition} from "../../databox/types";
import Twig from "twig";

export type FieldMap = {
    name: string;
    locale: string;
    id: string;
    value: string;
    type: string;
    multivalue: boolean;
    readonly: boolean;
    labels: Record<string, string>;
    attributeDefinition: AttributeDefinition;
    twig?: Twig.template;
};

export type ConfigDataboxMapping = {
    databox: string;
    collections?: string;
    searchQuery?: string;
    workspaceSlug: string;
    recordsCollectionPath: string;
    copyTo: string;
    storiesCollectionPath: string;
    fieldMap: Record<string, FieldMap>;
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

export type SubDef = {
    name: string;
    mime_type?: string;
    permalink: {
        url: string;
    };
};

export type PhraseanetMetaStruct = {
    id: string;
    namespace: string;
    source: string;
    tagname: string;
    name: string;
    separator: string;
    thesaurus_branch: string;
    type: string;
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

export type PhraseanetSubDef = {
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
}

export type PhraseanetRecord = {
    resource_id: string;
    databox_id: string;
    base_id: string;
    record_id: string;
    collection_id: string;
    uuid: string;
    title: string;
    original_name: string;
    subdefs: SubDef[];
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
    subdefs: SubDef[];
    status: PhraseanetStatusBit[];
    metadata: PhraseanetMetadata[];
    children: PhraseanetRecord[];
};
