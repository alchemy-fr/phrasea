
enum SecurityMethod {
    Password = 'password',
    Authentication = 'authentication',
}

export type Publication = {
    id: string;
    slug: string;
    cssLink?: string;
    authorized: boolean;
    securityContainerId: string;
    authorizationError?: string;
    securityMethod: SecurityMethod;
}

export type Asset = {
    id: string;
    publication: Publication;
    mimeType: string;
    assetId: string | undefined;
    previewUrl: string;
    posterUrl: string;
    title: string;
    webVTTLink: string;
}
