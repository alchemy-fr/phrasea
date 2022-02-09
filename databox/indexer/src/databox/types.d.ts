
type AlternateUrl = {
    type: string;
    url: string;
}

type Source = {
    url: string;
    isPrivate?: boolean;
    alternateUrls?: AlternateUrl[];
    import?: boolean;
};

export type AssetInput = {
    source?: Source;
    key?: string;
    title?: string;
    collection?: string;
    attributes?: AttributeInput[];
    renditions?: RenditionInput[];
    generateRenditions?: boolean;
}

export type CollectionInput = {
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
