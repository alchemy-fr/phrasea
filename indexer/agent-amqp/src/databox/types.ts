
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
}
