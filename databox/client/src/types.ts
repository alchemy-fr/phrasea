import {ApiHydraObjectResponse} from './api/hydra';
import {AttributeType} from './api/attributes';

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

type GroupValue = {
    name: string;
    key: string | null;
    values: any[];
    type: AttributeType;
};

export type User = {
    id: string;
    username: string;
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
    tags: Tag[];
    owner?: User;
    workspace: Workspace;
    attributes: Attribute[];
    collections: Collection[];
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

export interface Attribute extends IPermissions {
    id: string;
    definition: AttributeDefinition;
    origin: 'human' | 'machine';
    multiple: boolean;
    originVendor?: string;
    locale?: string | undefined;
    originUserId?: string;
    originVendorContext?: string;
    value: AttrValue;
    highlight: AttrValue;
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
    class: AttributeClass | string | null;
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
    groupId?: string;
    workspaceId?: string;
    collectionId?: string;
    include: Tag[];
    exclude: Tag[];
}

export interface Tag extends ApiHydraObjectResponse {
    id: string;
    name: string;
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

export interface Workspace extends IPermissions {
    id: string;
    name: string;
    collections: Collection[];
    enabledLocales?: string[] | undefined;
    localeFallbacks?: string[] | undefined;
    createdAt: string;
    public: boolean;
}

export type IntegrationData = {
    id: string;
    keyId: string | null;
    name: string;
    value: any;
};

export interface WorkspaceIntegration {
    id: string;
    title: string;
    integration: string;
    data: IntegrationData[];
    config: object;
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
