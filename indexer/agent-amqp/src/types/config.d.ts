
type AlternateUrlConfig = {
    name: string;
    pathPattern: string;
}

export type Config = {
    alternateUrls?: AlternateUrlConfig[];
}
