import type {WithTranslations} from '@alchemy/react-form';
import {Integration} from './components/Integration/types.ts';
import {AssetAnnotation} from './components/Media/Asset/Annotations/annotationTypes.ts';
import {RenditionBuildMode} from './api/rendition.ts';
import {DefinitionBase} from './components/Dialog/Workspace/DefinitionManager/DefinitionManager.tsx';
import React from 'react';
import {AttributeType} from './api/types.ts';
import {SortBy} from './components/Media/Search/Filter';
import {AQLQueries} from './components/Media/Search/AQL/query.ts';
import {ApiHydraObjectResponse} from '@alchemy/api';

export type AlternateUrl = {
    type: string;
    url: string;
    label?: string;
};

export type FileAnalysis = Record<string, any>;

export interface ApiFile extends Entity {
    url?: string;
    type: string;
    alternateUrls: AlternateUrl[];
    size: number;
    metadata?: Record<string, any>;
    accepted?: boolean;
    analysis?: FileAnalysis | null | undefined;
    analysisPending: boolean;
}

export type GroupValue = {
    name: string;
    key: string | null;
    values: any[];
    type: AttributeType;
};

export type User = {
    username: string;
    removed: boolean;
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

export type AssetAttachment = {
    asset?: Asset;
    file: ApiFile;
    name?: string | undefined;
    resolvedName: string;
    priority: number;
    updatedAt: Readonly<string>;
    createdAt: Readonly<string>;
} & Entity;

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
    attachments: AssetAttachment[];
    referenceCollection?: Collection | undefined;
    collections: Collection[] | undefined;
    main: AssetRendition | null;
    preview: AssetRendition | null;
    source: ApiFile | undefined;
    thumbnail: AssetRendition | null;
    animatedThumbnail: AssetRendition | null;
    createdAt: string;
    updatedAt: string;
    editedAt: string;
    attributesEditedAt: string;
    groupValue?: GroupValue | undefined;
    topicSubscriptions?: TopicSubscriptions;
    storyCollection?: Collection | undefined;
    deleted?: boolean;
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
    file: ApiFile;
    name: string;
    createdAt: string;
}

export interface AttributeDefinition extends IPermissions, Entity {
    name: string;
    nameTranslated?: string;
    slug: string;
    searchSlug: string;
    enabled: boolean;
    fieldType: AttributeType;
    entityList?: EntityList | string | null | undefined;
    multiple: boolean;
    searchable: boolean;
    editable: boolean;
    editableInGui: boolean;
    sortable: boolean;
    suggest: boolean;
    translatable: boolean;
    locales?: string[];
    allowInvalid: boolean;
    facetEnabled: boolean;
    canEdit: boolean;
    builtIn?: boolean;
    widget?: FieldWidget;
    widgetProps?: Record<string, any>;
    searchBoost: number;
    fallback: Record<string, string>;
    initialValues: Record<string, string>;
    workspace: Workspace | string;
    policy: AttributePolicy | string | null;
    lastErrors?: LastErrors;
    entityIri?: string | undefined;
    resolveLabel?: (entity: object) => string;
    getValueFromAsset?: (asset: Asset) => any;
    target: AssetType;
}

export type FieldWidget<P extends {} = any> = {
    component: React.FC<P>;
    props?: Partial<P>;
};

export interface AttributePolicy extends ApiHydraObjectResponse, Entity {
    name: string;
    public: boolean;
    editable: boolean;
    workspace: Workspace | string;
}

export interface RenditionPolicy extends ApiHydraObjectResponse, Entity {
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
    nameTranslated: string;
    parent?: RenditionDefinition | string | undefined | null;
    policy: RenditionPolicy | string | null;
    workspace: Workspace | string;
    definition: string;
    buildMode?: RenditionBuildMode | string;
    substitutable: boolean;
    useAsMain?: boolean;
    useAsPreview?: boolean;
    useAsThumbnail?: boolean;
    useAsAnimatedThumbnail?: boolean;
    priority: number;
    target: AssetType;
}

export interface AssetRendition extends ApiHydraObjectResponse, Entity {
    name: string;
    nameTranslated: string;
    file: ApiFile | undefined;
    ready: boolean;
    dirty?: boolean;
    projection?: boolean;
    locked: boolean;
    substituted: boolean;
    definition: Pick<RenditionDefinition, 'id' | 'substitutable'>;
}

export interface RenditionPolicy extends ApiHydraObjectResponse, Entity {
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
    allowed: RenditionPolicy[];
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

export type KeyTranslations = {
    [locale: string]: string;
};

type EntitySynonyms = {
    [locale: string]: string[];
};

export type AttributeEntity = {
    type: string;
    locale: string;
    value: string;
    translations: KeyTranslations;
    synonyms?: EntitySynonyms;
    createdAt: string;
    updatedAt: string;
} & ApiHydraObjectResponse &
    Entity;

export type EntityList = {
    name: string;
    definitions: AttributeDefinition[];
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

export interface Collection
    extends IPermissions,
        Entity,
        ApiHydraObjectResponse {
    title: string;
    titleTranslated: string;
    titleHighlight?: string;
    storyAsset?: Asset;
    absoluteTitle?: string;
    absoluteTitleTranslated?: string;
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
    deleted?: boolean;
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

export enum AttributeListItemType {
    Definition = 0,
    BuiltIn = 1,
    Divider = 2,
    Spacer = 3,
}

export type AttributeListItem = {
    id: string;
    type: AttributeListItemType;
    key?: string;
    definition?: string;
    displayEmpty?: boolean;
    format?: string;
};

export interface AttributeList extends IPermissions, Entity {
    title: string;
    description?: string;
    items?: AttributeListItem[];
    exclusive?: boolean; // if true, only items in this list well be shown otherwise all attributes
    public?: boolean;
    createdAt: string;
    updatedAt: string;
    owner?: User;
}

export type SavedSearchData = {
    query?: string;
    conditions: AQLQueries;
    sortBy: SortBy[];
};

export interface SavedSearch extends IPermissions, Entity {
    title: string;
    exclusive?: boolean; // if true, only items in this list will be shown otherwise all attributes
    public?: boolean;
    createdAt: string;
    updatedAt: string;
    data: SavedSearchData;
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
    nameTranslated: string;
    fileAnalyzers?: string;
    trashRetentionDelay?: number;
    enabledLocales?: string[] | undefined;
    localeFallbacks?: string[] | undefined;
    owner?: User;
    createdAt: string;
    public: boolean;
}

export type IntegrationData = {
    object?: object | undefined;
    keyId: string | null;
    name: string;
    value: any;
} & Entity;

export type IntegrationConfigKey = {
    label: string;
    description: string;
    value: string | undefined;
};

export interface WorkspaceIntegration extends DefinitionBase {
    title: string;
    enabled: boolean;
    integration: Integration;
    integrationTitle: string;
    data: IntegrationData[];
    config: object;
    configYaml: string;
    configInfo?: IntegrationConfigKey[];
    tokens: IntegrationToken[];
    workspace: Workspace | string;
    owner?: User;
    if?: string;
    needs?: string[];
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

export enum AssetType {
    Asset = 1,
    Story = 2,
    Both = 3,
}

export enum AssetTypeFilter {
    All = 0,
    Asset = 1,
    Story = 2,
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
export type StateSetterHandler<T> = (handler: (prev: T) => T) => void;

export type AssetOrAssetContainer = {} & Entity;

export interface Entity {
    id: string;
}

export type TopicSubscriptions<T extends string = string> = Record<T, boolean>;
