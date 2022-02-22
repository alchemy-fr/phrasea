
type AlternateUrl = {
    type: string;
    url: string;
}

type Source = {
    url: string;
    isPrivate?: boolean;
    alternateUrls?: AlternateUrl[];
    importFile?: boolean;
};

export type AssetInput = {
    source?: Source;
    key?: string;
    title?: string;
    collection?: string;
    workspace?: string;
    workspaceId?: string;
    attributes?: AttributeInput[];
    renditions?: RenditionInput[];
    generateRenditions?: boolean;
}

export type CollectionInput = {
    workspace?: string;
    workspaceId?: string;
    title?: string;
    parent?: string;
    key?: string;
}

export type AttributeInput = {
    definition: string;
    value: any;
    origin?: string;
    originVendor?: string;
    originUserId?: string;
    originVendorContext?: string;
    coordinates?: string;
    status?: string;
    confidence?: number;
}

export type RenditionInput = {
    definition: string;
    source?: Source;
}

export type RenditionClass = {
    id: string;
    name: string;
}
