import type {Translations} from '@alchemy/i18n';

export type TermsConfig = {
    text?: string;
    url?: string;
    enabled: boolean;
};

export enum SecurityMethod {
    Public = '',
    Password = 'password',
    Authentication = 'authentication',
}

type EntityWithIri = {
    '@id': string;
    'id': string;
};

type LayoutOptions = {
    displayMap?: boolean;
    displayMapPins?: boolean;
    logoUrl?: string;
};

export enum AuthorizationError {
    NotAllowed = 'not_allowed',
}

export enum LayoutEnum {
    Gallery = 'gallery',
    Grid = 'grid',
    Download = 'download',
    Mapbox = 'mapbox',
}

type BasePublication = {
    slug: string;
    authorized: boolean;
    securityContainerId: string;
    securityMethod: SecurityMethod;
    authorizationError?: AuthorizationError;
} & EntityWithIri;

export type UnauthorizedPublication = Partial<Publication> & BasePublication;

export type PublicationConfig = {
    enabled: boolean;
    downloadViaEmail: boolean;
    includeDownloadTermsInZippy: boolean;
    css: string | undefined;
    layout: LayoutEnum | undefined;
    theme: string | undefined;
    publiclyListed: boolean;
    downloadEnabled: boolean;
    beginsAt: Date | string | undefined | null;
    expiresAt: Date | string | undefined | null;
    terms: TermsConfig | undefined;
    downloadTerms: TermsConfig | undefined;
    securityMethod: SecurityMethod;
    securityOptions: Record<string, any> | undefined;
    mapOptions: Record<string, any> | undefined;
    layoutOptions: LayoutOptions | undefined;
};

export type PublicationProfile = {
    name: string;
    ownerId: string | undefined;
    config: PublicationConfig;
    publicationCount: number;
} & EntityWithIri;

export type Publication = {
    profile: PublicationProfile | string | null | undefined;
    cssLink?: string;
    config: PublicationConfig;
    archiveDownloadUrl?: string;
    parent?: Publication | undefined;
    rootPublication?: Publication | undefined;
    parentId?: string;
    downloadViaEmail?: boolean;
    downloadEnabled?: boolean;
    title: string;
    assets: Asset[];
    cover: Asset;
    terms: TermsConfig;
    children: Publication[];
    layout: LayoutEnum;
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
} & BasePublication;

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
