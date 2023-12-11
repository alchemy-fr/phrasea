type TermsConfig = {
    text?: string;
    url?: string;
    enabled: boolean;
};

enum SecurityMethod {
    Password = 'password',
    Authentication = 'authentication',
}

type LayoutOptions = {
    displayMap?: boolean;
    displayMapPins?: boolean;
    logoUrl?: string;
};

export type Publication = {
    id: string;
    slug: string;
    cssLink?: string;
    authorized: boolean;
    securityContainerId: string;
    authorizationError?: string;
    securityMethod: SecurityMethod;
    parent?: Publication | undefined;
    downloadViaEmail?: boolean;
    downloadEnabled?: boolean;
    title: string;
    assets: Asset[];
    cover?: Asset;
    children?: Publication[];
    layoutOptions: LayoutOptions;
    downloadTerms?: TermsConfig;
    description?: string;
    date: string;
};

export type Asset = {
    id: string;
    publication: Publication;
    mimeType: string;
    assetId: string | undefined;
    previewUrl: string;
    posterUrl: string;
    title: string;
    description?: string;
    webVTTLink: string;
    downloadUrl: string;
    thumbUrl?: string;
    originalName?: string;
    subDefinitions: SubDefinition[];
};

type SubDefinition = {
    id: string;
    name?: string;
    url: string;
    downloadUrl: string;
    size: string;
    mimeType: string;
    createdAt: string;
};
