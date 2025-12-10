import type {Translations} from '@alchemy/i18n';

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
    translations?: Translations;
    date: string;
    enabled: boolean;
};

export type WebVTT = {
    id: string;
    kind?: 'subtitles' | 'captions' | 'descriptions' | 'chapters' | 'metadata';
    label: string;
    locale: string;
    url: string;
};

export type WebVTTs = WebVTT[];

export type Asset = {
    id: string;
    publication: Publication;
    mimeType: string;
    assetId: string | undefined;
    previewUrl: string;
    posterUrl: string;
    title: string;
    description?: string;
    webVTTLinks?: WebVTTs;
    downloadUrl: string;
    thumbUrl?: string;
    originalName?: string;
    subDefinitions: SubDefinition[];
    translations: Translations;
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
