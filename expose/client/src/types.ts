import type {Translations} from '@alchemy/i18n';

export type TermsConfig = {
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

export enum AuthorizationError {
    NotAllowed = 'not_allowed',
}

export type Publication = {
    id: string;
    slug: string;
    cssLink?: string;
    authorized: boolean;
    archiveDownloadUrl?: string;
    securityContainerId: string;
    authorizationError?: AuthorizationError;
    securityMethod: SecurityMethod;
    parent?: Publication | undefined;
    rootPublication?: Publication | undefined;
    parentId?: string;
    downloadViaEmail?: boolean;
    downloadEnabled?: boolean;
    title: string;
    assets: Asset[];
    cover?: Asset;
    terms?: TermsConfig;
    children?: Publication[];
    layoutOptions: LayoutOptions;
    downloadTerms?: TermsConfig;
    description?: string;
    translations?: Translations;
    date: string;
    enabled: boolean;
    publiclyListed: boolean;
    capabilities: {
        edit: boolean;
        delete: boolean;
        operator: boolean;
    };
    urls?: PublicationUrl[];
    copyrightText?: string;
};

export type PublicationUrl = {
    text: string;
    url: string;
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

export enum SortBy {
    Date = 'date',
    Name = 'name',
}

export type Thumb = {
    id: string;
    src?: string;
    mimeType: string;
    alt: string;
    path: string;
    width?: number;
    height?: number;
};

export type ThumbWithDimensions = {
    width: number;
    height: number;
} & Omit<Thumb, 'width' | 'height'>;
