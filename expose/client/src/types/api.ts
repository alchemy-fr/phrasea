/**
 * Contracts of the Expose API consumed by the client.
 */

export type HydraCollection<T> = {
    member: T[];
    totalItems: number;
    view?: {
        next?: string;
        previous?: string;
    };
};

export type Page<T> = {
    total: number;
    items: T[];
    next?: string;
    previous?: string;
};

/** `{field: {locale: value}}` */
export type Translations = Record<string, Record<string, string>>;

export type TermsConfig = {
    enabled?: boolean | null;
    text?: string | null;
    url?: string | null;
};

export enum SecurityMethod {
    Password = 'password',
    Authentication = 'authentication',
}

export enum AuthorizationError {
    NotAllowed = 'not_allowed',
    MissingPassword = 'missing_password',
    InvalidPassword = 'invalid_password',
}

export enum Layout {
    Gallery = 'gallery',
    Grid = 'grid',
    Download = 'download',
    Mapbox = 'mapbox',
}

export type LayoutOptions = {
    logoUrl?: string | null;
};

/**
 * The configuration of a publication or a profile, as stored: `null` means
 * "not set", which for a publication falls back to the value of its profile.
 */
export type PublicationConfig = {
    enabled?: boolean | null;
    publiclyListed?: boolean | null;
    downloadEnabled?: boolean | null;
    downloadViaEmail?: boolean | null;
    includeDownloadTermsInArchive?: boolean | null;
    beginsAt?: string | null;
    expiresAt?: string | null;
    layout?: Layout | null;
    securityMethod?: SecurityMethod | null;
    securityOptions?: {password?: string | null} | [] | null;
    terms?: TermsConfig | null;
    downloadTerms?: TermsConfig | null;
    layoutOptions?: LayoutOptions | null;
};

export type WebVTTLink = {
    id: string;
    kind?: 'subtitles' | 'captions' | 'descriptions' | 'chapters' | 'metadata';
    label?: string;
    locale?: string;
    url: string;
};

export type SubDefinition = {
    id: string;
    name?: string;
    url?: string;
    downloadUrl?: string;
    size?: number;
    mimeType?: string;
};

export type Asset = {
    '@id'?: string;
    'id': string;
    /** The Databox asset the Expose asset comes from */
    'assetId'?: string | null;
    'trackingId'?: string | null;
    /** Addresses the asset in the URL of its publication */
    'slug'?: string | null;
    'title'?: string | null;
    'description'?: string | null;
    'translations'?: Translations | null;
    'originalName'?: string;
    'mimeType': string;
    'size'?: number;
    'previewUrl'?: string | null;
    'previewType'?: string | null;
    'thumbUrl'?: string | null;
    'thumbType'?: string | null;
    'posterUrl'?: string | null;
    'downloadUrl'?: string | null;
    'webVTTLinks'?: WebVTTLink[] | null;
    'subDefinitions'?: SubDefinition[];
    /** Summary of the publication, on `GET /assets/{id}` */
    'publication'?: Publication;
};

export type Capabilities = {
    edit?: boolean;
    delete?: boolean;
    operator?: boolean;
};

export type PublicationProfile = {
    '@id'?: string;
    'id': string;
    'name': string;
    'config': PublicationConfig;
    'publicationCount'?: number;
    'capabilities'?: Capabilities;
};

/** A parent, child or root publication, embedded in another one */
export type PublicationRef = {
    id: string;
    slug?: string | null;
    title?: string | null;
    translations?: Translations | null;
    parentId?: string | null;
};

/**
 * A publication. Without access to its details (`authorized: false`), only
 * what the security screen needs is set — plus the list fields when it is
 * publicly listed.
 */
export type Publication = {
    '@id'?: string;
    'id': string;
    'slug'?: string | null;
    'title'?: string | null;
    'description'?: string | null;
    'translations'?: Translations | null;
    'date'?: string | null;
    'createdAt'?: string;
    'enabled'?: boolean;
    'publiclyListed'?: boolean;
    'layout'?: Layout;
    'layoutOptions'?: LayoutOptions;
    'cover'?: Asset | null;
    'assets'?: Asset[];
    'parent'?: PublicationRef | null;
    'parentId'?: string | null;
    'rootPublication'?: PublicationRef | null;
    'children'?: PublicationRef[];
    'terms'?: TermsConfig;
    'downloadTerms'?: TermsConfig;
    'downloadEnabled'?: boolean;
    'downloadViaEmail'?: boolean;
    'archiveDownloadUrl'?: string | null;
    'cssLink'?: string | null;
    'authorized': boolean;
    'securityMethod'?: SecurityMethod | null;
    'securityContainerId'?: string | null;
    'authorizationError'?: AuthorizationError | string | null;
    'capabilities'?: Capabilities;
    /** Read by the editors only */
    'profile'?: PublicationProfile | null;
    /** The raw configuration, read by the editors only */
    'config'?: PublicationConfig;
};
