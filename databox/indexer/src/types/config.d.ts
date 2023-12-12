type AlternateUrlConfig = {
    name: string;
    pathPattern: string;
};

export type ConfigOptions = Record<string, any>;

export type IndexLocation<T extends ConfigOptions> = {
    name: string;
    type: string;
    options: T;
    alternateUrls?: AlternateUrlConfig[];
};

export type Config = {
    databox: {
        url?: string;
        clientId?: string;
        clientSecret?: string;
        ownerId?: string;
        verifySSL?: boolean;
        concurrency?: number;
    };
    whitelist: string[];
    blacklist: string[];
    alternateUrls?: AlternateUrlConfig[];
    locations: IndexLocation<any>[];
};
