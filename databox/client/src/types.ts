import {ApiHydraObjectResponse} from './api/hydra';
import {AttributeType} from './api/attributes';
import type {WithTranslations} from '@alchemy/react-form';
import {Integration} from './components/Integration/types.ts';
import {AssetAnnotation} from './components/Media/Asset/Annotations/annotationTypes.ts';
import {RenditionBuildMode} from './api/rendition.ts';
import {DefinitionBase} from './components/Dialog/Workspace/DefinitionManager/DefinitionManager.tsx';

type AlternateUrl = {
    type: string;
    url: string;
    label?: string;
};

export interface File extends Entity {
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
    username: string;
} & Entity;

export type ShareAlternateUrl = {
    name: string;
    url: string;
    type: string | undefined;
};

export type Share = {
    title?: string | undefined;
    asset: Asset;
    token: string;
    startsAt?: string | undefined | null;
    expiresAt?: string | undefined | null;
    updatedAt: Readonly<string>;
    createdAt: Readonly<string>;
    alternateUrls: ShareAlternateUrl[];
} & Entity;

export type ESDocumentState = {
    synced: boolean;
    data: object;
};

export interface Asset
    extends IPermissions<{
            canEditAttributes: boolean;
            canShare: boolean;
        }>,
        Entity {
    title?: string | undefined;
    resolvedTitle?: string;
    titleHighlight: string | null;
    description?: string;
    privacy: number;
    tags: Tag[] | undefined;
    owner?: User;
    threadKey: string;
    thread?: Thread | undefined;
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
    topicSubscriptions?: TopicSubscriptions;
}

type AttrValue = any;

type AttributeOrigin = 'human' | 'machine' | 'fallback' | 'initial';

export interface Attribute extends IPermissions, Entity {
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

export interface AssetFileVersion extends Entity {
    asset: Asset;
    file: File;
    name: string;
    createdAt: string;
}

export interface AttributeDefinition extends IPermissions, Entity {
    name: string;
    slug: string;
    enabled: boolean;
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
    lastErrors?: LastErrors;
}

export interface AttributeClass extends ApiHydraObjectResponse, Entity {
    name: string;
    public: boolean;
    editable: boolean;
    workspace: Workspace | string;
}

export interface FieldType extends ApiHydraObjectResponse {
    name: string;
    title: string;
}

export interface RenditionDefinition extends ApiHydraObjectResponse, Entity {
    name: string;
    parent?: RenditionDefinition | string | undefined | null;
    class: AttributeClass | string | null;
    workspace: Workspace | string;
    definition: string;
    buildMode?: RenditionBuildMode | string;
    useAsOriginal?: boolean;
    useAsPreview?: boolean;
    useAsThumbnail?: boolean;
    useAsThumbnailActive?: boolean;
    priority: number;
}

export interface AssetRendition extends ApiHydraObjectResponse, Entity {
    name: string;
    file: File | undefined;
    ready: boolean;
    dirty?: boolean;
    projection?: boolean;
    locked: boolean;
    substituted: boolean;
}

export interface RenditionClass extends ApiHydraObjectResponse, Entity {
    name: string;
    workspace: Workspace | string;
    public: boolean;
}

export interface RenditionRule extends ApiHydraObjectResponse, Entity {
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

export interface TagFilterRule extends ApiHydraObjectResponse, Entity {
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
    type: string;
    locale: string;
    value: string;
    translations: KeyTranslations;
    createdAt: string;
    updatedAt: string;
} & ApiHydraObjectResponse &
    Entity;

export interface Tag extends ApiHydraObjectResponse, WithTranslations, Entity {
    name: string;
    nameTranslated: string;
    color: string | null;
    workspace: Workspace | string;
}

export interface Group extends Entity {
    name: string;
}

export type CollectionOptionalWorkspace = {workspace?: Workspace} & Omit<
    Collection,
    'workspace'
>;

export interface Collection extends IPermissions, Entity {
    title: string;
    absoluteTitle?: string;
    children?: CollectionOptionalWorkspace[];
    workspace: Workspace;
    public: boolean;
    shared: boolean;
    privacy: number;
    inheritedPrivacy?: number;
    createdAt: string;
    updatedAt: string;
    owner?: User;
    topicSubscriptions?: TopicSubscriptions;
}

export interface Basket extends IPermissions, Entity {
    title: string;
    titleHighlight?: string | undefined;
    description?: string | undefined;
    descriptionHighlight?: string | undefined;
    assetCount?: number;
    createdAt: string;
    updatedAt: string;
    owner?: User;
}

export interface Thread extends Entity {
    id: string;
    key: string;
    createdAt: string;
}

export type MessageAttachment = {
    type: string;
    content: string;
};

export type DeserializedMessageAttachment = {
    id?: string;
    type: string;
    data: Record<string, any>;
};

export interface ThreadMessage extends Entity {
    id: string;
    content: string;
    attachments?: MessageAttachment[];
    author: User;
    createdAt: string;
    updatedAt: string;
    acknowledged?: boolean;
    capabilities: {
        canDelete: boolean;
        canEdit: boolean;
    };
}

export interface BasketAsset extends Entity {
    asset: Asset;
    context?: any;
    titleHighlight: string;
    position: number;
    createdAt: string;
    owner?: User;
    assetAnnotations?: AssetAnnotation[];
}

export type LastErrors = {
    date: string;
    message: string;
    code: number;
    file: string;
    line: number;
}[];

export interface Workspace extends IPermissions, Entity {
    name: string;
    enabledLocales?: string[] | undefined;
    localeFallbacks?: string[] | undefined;
    createdAt: string;
    public: boolean;
}

export type IntegrationData = {
    object?: object | undefined;
    keyId: string | null;
    name: string;
    value: any;
} & Entity;

export interface WorkspaceIntegration extends DefinitionBase {
    title: string;
    enabled: boolean;
    integration: Integration;
    integrationTitle: string;
    data: IntegrationData[];
    config: object;
    configYaml: string;
    tokens: IntegrationToken[];
    workspace: Workspace | string;
    lastErrors?: LastErrors;
}

export interface IntegrationType {
    id: string;
    title: string;
    name: string;
    reference: string;
}

export type IntegrationToken = {
    userId: string;
    expired: boolean;
    expiresAt: string;
    createdAt: string;
} & Entity;

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
    mask: number;
    userId: string | null;
    userType: UserType;
    resolving?: boolean;
} & Entity;

export type StateSetter<T> = (handler: T | ((prev: T) => T)) => void;

export type AssetOrAssetContainer = {} & Entity;

export interface Entity {
    id: string;
}

export type TopicSubscriptions<T extends string = string> = Record<T, boolean>;
