
type AlternateUrlConfig = {
    name: string;
    pathPattern: string;
}

export type IndexLocation = {
    name: string,
    "type": string,
    "options": Record<string, any>
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
    locations: IndexLocation[];
}
