import {ApiHydraObjectResponse} from './api/hydra';
import {AttributeType} from './api/attributes';
import type {WithTranslations} from '@alchemy/react-form';
import {Integration} from './components/Integration/types.ts';

type AlternateUrl = {
    type: string;
    url: string;
    label?: string;
};

export interface File {
    id: string;
    url?: string;
    type: string;
    alternateUrls: AlternateUrl[];
    size: number;
}

export type GroupValue = {
    name: string;
    key: string | null;
    values: any[];
    type: AttributeType;
};

export type User = {
    id: string;
    username: string;
};

export type ShareAlternateUrl = {
    name: string;
    url: string;
    type: string | undefined;
};

export type Share = {
    id: string;
    title?: string | undefined;
    asset: Asset;
    token: string;
    startsAt?: string | undefined | null;
    expiresAt?: string | undefined | null;
    updatedAt: Readonly<string>;
    createdAt: Readonly<string>;
    alternateUrls: ShareAlternateUrl[];
};

export interface Asset
    extends IPermissions<{
        canEditAttributes: boolean;
        canShare: boolean;
    }> {
    id: string;
    title?: string | undefined;
    resolvedTitle?: string;
    titleHighlight: string | null;
    description?: string;
    privacy: number;
    tags: Tag[] | undefined;
    owner?: User;
    workspace: Workspace;
    attributes: Attribute[];
    referenceCollection?: Collection | undefined;
    collections: Collection[] | undefined;
    original: AssetRendition | null;
    preview: AssetRendition | null;
    source: File | undefined;
    thumbnail: AssetRendition | null;
    thumbnailActive: AssetRendition | null;
    createdAt: string;
    updatedAt: string;
    editedAt: string;
    pendingSourceFile: boolean;
    pendingUploadToken?: string;
    attributesEditedAt: string;
    groupValue?: GroupValue | undefined;
}

type AttrValue = any;

type AttributeOrigin = 'human' | 'machine' | 'fallback' | 'initial';

export interface Attribute extends IPermissions {
    id: string;
    definition: AttributeDefinition;
    origin: AttributeOrigin;
    multiple: boolean;
    originVendor?: string;
    locale?: string | undefined;
    originUserId?: string;
    originVendorContext?: string;
    value: AttrValue;
    highlight: AttrValue;
    assetAnnotations?: AssetAnnotation[];
}

export interface AssetFileVersion {
    id: string;
    asset: Asset;
    file: File;
    name: string;
    createdAt: string;
}

export interface AttributeDefinition extends IPermissions {
    id: string;
    name: string;
    slug: string;
    fieldType: string;
    entityType?: string | undefined;
    multiple: boolean;
    searchable: boolean;
    suggest: boolean;
    translatable: boolean;
    locales?: string[];
    allowInvalid: boolean;
    canEdit: boolean;
    searchBoost: number;
    fallback: Record<string, string>;
    initialValues: Record<string, string>;
    workspace: Workspace | string;
    class: AttributeClass | string | null;
}

export interface AttributeClass extends ApiHydraObjectResponse {
    id: string;
    name: string;
    public: boolean;
    editable: boolean;
    workspace: Workspace | string;
}

export interface FieldType extends ApiHydraObjectResponse {
    name: string;
    title: string;
}

export interface RenditionDefinition extends ApiHydraObjectResponse {
    id: string;
    name: string;
    parent?: RenditionDefinition | string | undefined | null;
    class: AttributeClass | string | null;
    workspace: Workspace | string;
    definition: string;
    pickSourceFile?: boolean;
    useAsOriginal?: boolean;
    useAsPreview?: boolean;
    useAsThumbnail?: boolean;
    useAsThumbnailActive?: boolean;
    priority: number;
}

export interface AssetRendition extends ApiHydraObjectResponse {
    id: string;
    name: string;
    file: File | undefined;
    ready: boolean;
    dirty?: boolean;
    projection?: boolean;
    locked: boolean;
    substituted: boolean;
}

export interface RenditionClass extends ApiHydraObjectResponse {
    id: string;
    name: string;
    workspace: Workspace | string;
    public: boolean;
}

export interface RenditionRule extends ApiHydraObjectResponse {
    id: string;
    name: string;
    userId: string | null;
    groupId: string | null;
    workspaceId: string | null;
    collectionId: string | null;
    group: Group | null;
    user: User | null;
    allowed: RenditionClass[];
}

export type TPermission<E extends Record<string, boolean> = {}> = {
    canEdit: boolean;
    canDelete: boolean;
    canEditPermissions: boolean;
} & E;

export interface IPermissions<E extends Record<string, boolean> = {}>
    extends ApiHydraObjectResponse {
    capabilities: TPermission<E>;
}

export interface TagFilterRule extends ApiHydraObjectResponse {
    id: string;
    userId?: string;
    username?: string;
    groupId?: string;
    groupName?: string;
    workspaceId?: string;
    collectionId?: string;
    include: Tag[];
    exclude: Tag[];
}

type KeyTranslations = {
    [locale: string]: string;
};

export type AttributeEntity = {
    id: string;
    type: string;
    locale: string;
    value: string;
    translations: KeyTranslations;
    createdAt: string;
    updatedAt: string;
} & ApiHydraObjectResponse;

export interface Tag extends ApiHydraObjectResponse, WithTranslations {
    id: string;
    name: string;
    nameTranslated: string;
    color: string | null;
    workspace: Workspace | string;
}

export interface Group {
    id: string;
    name: string;
}

export type CollectionOptionalWorkspace = {workspace?: Workspace} & Omit<
    Collection,
    'workspace'
>;

export interface Collection extends IPermissions {
    id: string;
    title: string;
    absoluteTitle?: string;
    children?: CollectionOptionalWorkspace[];
    workspace: Workspace;
    public: boolean;
    shared: boolean;
    privacy: number;
    inheritedPrivacy: number;
    createdAt: string;
    updatedAt: string;
    owner?: User;
}

export interface Basket extends IPermissions {
    id: string;
    title: string;
    titleHighlight?: string | undefined;
    description?: string | undefined;
    descriptionHighlight?: string | undefined;
    assetCount?: number;
    createdAt: string;
    updatedAt: string;
    owner?: User;
}

export interface BasketAsset {
    id: string;
    asset: Asset;
    context?: any;
    titleHighlight: string;
    position: number;
    createdAt: string;
    owner?: User;
    assetAnnotations?: AssetAnnotation[];
}

export interface Workspace extends IPermissions {
    id: string;
    name: string;
    enabledLocales?: string[] | undefined;
    localeFallbacks?: string[] | undefined;
    createdAt: string;
    public: boolean;
}

export type IntegrationData = {
    id: string;
    object?: object | undefined;
    keyId: string | null;
    name: string;
    value: any;
};

export interface WorkspaceIntegration {
    id: string;
    title: string;
    integration: Integration;
    data: IntegrationData[];
    config: object;
    tokens: IntegrationToken[];
}

export type IntegrationToken = {
    id: string;
    userId: string;
    expired: boolean;
    expiresAt: string;
    createdAt: string;
};

export enum UserType {
    User = 'user',
    Group = 'group',
}

export enum CollectionOrWorkspace {
    Collection = 'collection',
    Workspace = 'workspace',
}

export type Ace = (
    | {
          userType: UserType.Group;
          group?: Group | null;
      }
    | {
          userType: UserType.User;
          user?: User | null;
      }
) & {
    id: string;
    mask: number;
    userId: string | null;
    userType: UserType;
    resolving?: boolean;
};

export type StateSetter<T> = (handler: T | ((prev: T) => T)) => void;

export type AssetOrAssetContainer = {
    id: string;
};

export enum AnnotationType {
    Point = 'point',
    Circle = 'circle',
    Rect = 'rect',
    Cue = 'cue',
    TimeRange = 'time_range',
}

export type AssetAnnotation = {
    type: AnnotationType;
    [prop: string]: any;
};
