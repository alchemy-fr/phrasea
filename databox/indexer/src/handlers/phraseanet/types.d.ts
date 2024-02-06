export type ConfigDataboxMapping = {
    sourceCollection: string;
    databoxId: string;
    searchQuery?: string;
    workspaceSlug: string;
    recordsCollectionPath: string;
    storiesCollectionPath: string;
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
    id: number;
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

export type PhraseanetCollection = {
    databox_id: number;
    base_id: number;
    collection_id: number;
    name: string;
};

type PhraseanetCaption = {
    meta_structure_id: number;
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
    subdefs: SubDef[];
    caption?: PhraseanetCaption[];
    status: PhraseanetStatusBit[];
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
    caption?: PhraseanetCaption[];
    status: PhraseanetStatusBit[];
    children: PhraseanetRecord[];
};

