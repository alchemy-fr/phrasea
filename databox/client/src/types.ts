import {ApiHydraObjectResponse} from "./api/hydra";

type AlternateUrl = {
    type: string;
    url: string;
    label?: string;
}

export interface File {
    id: string;
    url?: string;
    type: string;
    alternateUrls: AlternateUrl[];
    size: number;
}

export interface Asset extends IPermissions<{
    canEditAttributes: boolean;
    canShare: boolean;
}> {
    id: string;
    title?: string | undefined;
    resolvedTitle?: string;
    titleHighlight: string | null;
    description?: string;
    privacy: number;
    tags: Tag[];
    workspace: Workspace;
    attributes: Attribute[];
    collections: Collection[];
    original: AssetRendition | null;
    preview: AssetRendition | null;
    thumbnail: AssetRendition | null;
    thumbnailActive: AssetRendition | null;
    createdAt: string;
    updatedAt: string;
}

type AttrValue = any;

export interface Attribute extends IPermissions {
    id: string;
    definition: AttributeDefinition;
    origin: "human" | "machine";
    originVendor?: string;
    locale?: string | undefined;
    originUserId?: string;
    originVendorContext?: string;
    value: AttrValue;
    highlight: AttrValue;
}

export interface AttributeDefinition extends IPermissions {
    id: string;
    name: string;
    slug: string;
    fieldType: string;
    multiple: boolean;
    searchable: boolean;
    translatable: boolean;
    locales?: string[];
    allowInvalid: boolean;
    canEdit: boolean;
    searchBoost: number;
    fallback: Record<string, string>;
    workspace: Workspace | string;
    class: AttributeClass | string;
}

export interface AttributeClass extends ApiHydraObjectResponse {
    id: string;
    name: string;
    public: boolean;
    editable: boolean;
    workspace: Workspace | string;
}

export interface RenditionDefinition extends ApiHydraObjectResponse {
    id: string;
    name: string;
    class: AttributeClass | string;
    workspace: Workspace | string;
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
    allowed: RenditionClass[];
}

export type TPermission<E extends Record<string, boolean> = {}> = {
    canEdit: boolean,
    canDelete: boolean,
    canEditPermissions: boolean,
} & E;

export interface IPermissions<E extends Record<string, boolean> = {}> extends ApiHydraObjectResponse {
    capabilities: TPermission<E>;
}

export interface TagFilterRule extends ApiHydraObjectResponse {
    id: string;
    userId?: string;
    groupId?: string;
    workspaceId?: string;
    collectionId?: string;
    include: Tag[];
    exclude: Tag[];
}

export interface Tag extends ApiHydraObjectResponse {
    id: string;
    name: string;
    workspace: Workspace | string;
}

export interface User {
    id: string;
    username: string;
}

export interface Group {
    id: string;
    name: string;
}


export type CollectionOptionalWorkspace = { workspace?: Workspace } & Omit<Collection, "workspace">;

export interface Collection extends IPermissions {
    id: string;
    title: string;
    children?: CollectionOptionalWorkspace[];
    workspace: Workspace;
    privacy: number;
    createdAt: string;
    updatedAt: string;
}

export interface Workspace extends IPermissions {
    id: string;
    name: string;
    collections: Collection[];
    enabledLocales?: string[] | undefined;
    localeFallbacks?: string[] | undefined;
    createdAt: string;
}

export type IntegrationData = {
    id: string;
    keyId: string | null;
    name: string;
    value: string;
}

export interface WorkspaceIntegration {
    id: string;
    title: string;
    integration: string;
    data: IntegrationData[];
    options: object;
    supported?: boolean;
}

export enum UserType {
    User = 'user',
    Group = 'group',
}

export enum CollectionOrWorkspace {
    Collection = 'collection',
    Workspace = 'workspace',
}

export interface Ace {
    id: string;
    userType: UserType;
    userId: string | null;
    mask: number;
}
