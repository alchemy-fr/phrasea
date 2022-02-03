
type AlternateUrlConfig = {
    name: string;
    pathPattern: string;
}

export type ConfigOptions = Record<string, any>;

export type IndexLocation<T extends ConfigOptions> = {
    name: string,
    type: string,
    options: T;
}

export type Config = {
    databox: {
        url?: string;
        clientId?: string;
        clientSecret?: string;
        workspaceId?: string;
        collectionId?: string;
        ownerId?: string;
        verifySSL?: boolean;
    },
    alternateUrls?: AlternateUrlConfig[];
    locations: IndexLocation<any>[];
}
