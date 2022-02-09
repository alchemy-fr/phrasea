
type AlternateUrl = {
    type: string;
    url: string;
}

export type AssetInput = {
    source?: {
        url: string;
        isPrivate?: boolean;
        alternateUrls?: AlternateUrl[];
    };
    key?: string;
    title?: string;
    collection?: string;
    attributes?: AttributeInput[];
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
