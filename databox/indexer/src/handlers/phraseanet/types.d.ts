export type ConfigDataboxMapping = {
    databoxId: string;
    workspaceSlug: string;
}

export type PhraseanetConfig = {
    url: string;
    searchQuery?: string;
    searchOrder?: string;
    token: string;
    verifySSL?: boolean;
    importFiles?: boolean;
    databoxMapping: ConfigDataboxMapping[];
};

export type SubDef = {
    name: string;
    permalink: {
        url: string;
    }
};

export type PhraseanetMetaStruct = {
    id: number,
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
}

export type PhraseanetSubDef = {
    name: string;
    databox_id: number;
    class: string;
    downloadable: boolean;
    devices: string[];
    labels: Record<string, string>;
    options: Record<string, any>;
}

export type PhraseanetCollection = {
    databox_id: number;
    base_id: number;
    collection_id: number;
    name: string,
}

type PhraseanetCaption = {
    meta_structure_id: number;
    name: string;
    value: string;
}

export type PhraseanetRecord = {
    databox_id: string;
    base_id: string;
    record_id: string;
    collection_id: string;
    uuid: string;
    title: string;
    original_name: string;
    subdefs: SubDef[];
    caption?: PhraseanetCaption[];
}
