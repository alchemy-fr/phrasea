/**
 * Databox API contracts (API Platform / Hydra).
 * These types mirror the JSON payloads returned by `databox/api`.
 */

export interface HydraObject {
    '@id': string;
    '@type': string;
}

export interface Entity {
    id: string;
}

export type HydraCollection<T, E extends object = object> = {
    'hydra:totalItems': number;
    'hydra:member': T[];
    'hydra:view'?: {
        'hydra:first'?: string;
        'hydra:previous'?: string;
        'hydra:next'?: string;
        'hydra:last'?: string;
    };
} & E;

export type Page<T, E extends object = object> = {
    total: number;
    items: T[];
    next?: string;
    previous?: string;
} & E;

export type Capabilities<
    E extends Record<string, boolean> = Record<never, boolean>,
> = {
    edit: boolean;
    delete: boolean;
    editPermissions: boolean;
} & E;

export interface WithCapabilities<
    E extends Record<string, boolean> = Record<never, boolean>,
> extends HydraObject {
    capabilities: Capabilities<E>;
}

export type Translations = Record<string, string>;

export type User = Entity & {
    username: string;
    removed?: boolean;
};

export type Group = Entity & {
    name: string;
};

export enum UserType {
    User = 'user',
    Group = 'group',
}

// ---------------------------------------------------------------------------
// Enums

export enum AssetStatus {
    Accepted = 0,
    Pending = 1,
    Quarantined = 2,
}

export enum Privacy {
    Secret = 0,
    PrivateInWorkspace = 1,
    PublicInWorkspace = 2,
    Private = 3,
    PublicForUsers = 4,
    Public = 5,
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

export enum AttributeType {
    Boolean = 'boolean',
    Code = 'code',
    CollectionPath = 'collection_path',
    Color = 'color',
    Date = 'date',
    DateTime = 'date_time',
    Duration = 'duration',
    Entity = 'entity',
    FileType = 'file_type',
    GeoPoint = 'geo_point',
    Html = 'html',
    Id = 'id',
    Ip = 'ip',
    Json = 'json',
    Keyword = 'keyword',
    Number = 'number',
    Privacy = 'privacy',
    AssetStatus = 'assetStatus',
    Rendition = 'rendition',
    FileSize = 'filesize',
    Story = 'story',
    Tag = 'tag',
    Text = 'text',
    Textarea = 'textarea',
    User = 'user',
    WebVtt = 'web_vtt',
    Workspace = 'workspace',
}

export enum EntityName {
    Asset = 'assets',
    AssetPolicy = 'asset-policies',
    Attachment = 'attachments',
    Collection = 'collections',
    Workspace = 'workspaces',
    SavedSearch = 'saved-searches',
    Page = 'pages',
    AttributeDefinition = 'attribute-definitions',
    AttributePolicy = 'attribute-policies',
    RenditionDefinition = 'rendition-definitions',
    RenditionPolicy = 'rendition-policies',
    Rendition = 'renditions',
    BuiltInAttribute = 'built-in-attributes',
    AttributeEntity = 'attribute-entities',
    User = 'users',
    Tag = 'tags',
    AssetDataTemplate = 'asset-data-templates',
    OperationTask = 'operation-tasks',
    EntityList = 'entity-lists',
    Integration = 'integrations',
    IntegrationType = 'integration-types',
    Profile = 'profiles',
    Basket = 'baskets',
    Share = 'shares',
    Thread = 'threads',
    Message = 'messages',
    File = 'files',
    Workflow = 'workflows',
}

export enum AttributeBatchActionEnum {
    Set = 'set',
    Replace = 'replace',
    Add = 'add',
    Delete = 'delete',
}

export type AttributeBatchAction = {
    action?: AttributeBatchActionEnum;
    id?: string;
    ids?: string[];
    assets?: string[];
    value?: unknown;
    definitionId?: string;
    locale?: string;
    position?: number;
    origin?: 'human' | 'machine';
};

// ---------------------------------------------------------------------------
// Files & renditions

export type AlternateUrl = {
    type: string;
    url: string;
    label?: string;
};

export type FileUsageType = 'source' | 'version' | 'rendition';

export type FileUsage = {
    type: FileUsageType;
    assetId: string;
    assetTitle?: string | null;
    name?: string | null;
};

export interface ApiFile extends Entity, Partial<HydraObject> {
    url?: string;
    type: string;
    extension: string;
    alternateUrls: AlternateUrl[];
    size: number;
    docUniqueId: string;
    checksum: string;
    fileName: string;
    metadata?: Record<string, unknown>;
    accepted?: boolean;
    analysis?: Record<string, unknown> | null;
    analysisPending: boolean;
    usages?: FileUsage[];
}

export enum RenditionBuildMode {
    None = 0,
    PickSource = 1,
    Custom = 2,
}

export interface RenditionPolicy extends HydraObject, Entity {
    name: string;
    public: boolean;
    editable?: boolean;
    workspace: Workspace | string;
}

export interface RenditionDefinition extends HydraObject, Entity {
    name: string;
    displayName: string;
    parent?: RenditionDefinition | string | null;
    policy: RenditionPolicy | string | null;
    workspace: Workspace | string;
    definition: string;
    buildMode?: RenditionBuildMode;
    substitutable: boolean;
    writeMetadata: boolean;
    metadata?: Record<string, string> | null;
    useAsMain?: boolean;
    useAsPreview?: boolean;
    useAsThumbnail?: boolean;
    useAsAnimatedThumbnail?: boolean;
    priority: number;
    target: AssetType;
}

export interface AssetRendition extends HydraObject, Entity {
    name: string;
    displayName: string;
    file?: ApiFile;
    ready: boolean;
    dirty?: boolean;
    projection?: boolean;
    locked: boolean;
    substituted: boolean;
    definition: Pick<RenditionDefinition, 'id' | 'substitutable'> | null;
}

// ---------------------------------------------------------------------------
// Workspaces, collections, tags

export interface Workspace
    extends
        WithCapabilities<{createCollection: boolean; createAsset: boolean}>,
        Entity {
    name: string;
    displayName: string;
    slug?: string;
    translations?: Record<string, Translations>;
    fileAnalyzers?: string;
    trashRetentionDelay?: number;
    assetDefaultStatus?: AssetStatus;
    fileAnalysisRequired?: boolean;
    enabledLocales?: string[];
    localeFallbacks?: string[];
    owner?: User;
    createdAt: string;
    public: boolean;
}

export interface Tag extends HydraObject, Entity {
    name: string;
    displayName: string;
    color: string | null;
    translations?: Record<string, Translations>;
    workspace: Workspace | string;
}

export interface Collection
    extends
        WithCapabilities<{createAsset: boolean; createCollection: boolean}>,
        Entity {
    parentId?: string;
    parent?: Collection;
    name: string;
    displayName: string;
    nameHighlight?: string;
    translations?: Record<string, Translations>;
    storyAsset?: Asset;
    absoluteName?: string;
    absoluteDisplayName?: string;
    absolutePath?: string;
    children?: Collection[];
    workspace: Workspace;
    public: boolean;
    shared: boolean;
    privacy: Privacy;
    inheritedPrivacy?: Privacy;
    createdAt: string;
    updatedAt: string;
    owner?: User;
    topicSubscriptions?: string[];
    deleted?: boolean;
}

export type CollectionPrivacyInfo = {
    privacy: Privacy;
    computedPrivacy: Privacy;
    canEditAssetPrivacy: boolean;
};

// ---------------------------------------------------------------------------
// Attributes

export type AttributeWidgetOptions = Record<string, unknown>;

export interface BaseAttributeDefinition extends Entity {
    name: string;
    enabled: boolean;
    displayName: string;
    type: AttributeType;
    searchable: boolean;
    sortable: boolean;
    entityList?: EntityList | string | null;
    multiple: boolean;
    facetEnabled: boolean;
    slug: string;
    searchSlug: string;
    widgetOptions?: AttributeWidgetOptions;
    builtIn?: boolean;
}

export interface BuiltInAttribute extends BaseAttributeDefinition {
    builtIn: true;
}

export interface AttributeDefinition
    extends BaseAttributeDefinition, WithCapabilities {
    builtIn?: false;
    editable: boolean;
    editableInGui: boolean;
    suggest: boolean;
    translatable: boolean;
    locales?: string[];
    allowInvalid: boolean;
    fillFromName: boolean;
    namePriority?: number | null;
    canEdit: boolean;
    searchBoost: number;
    fallback: Record<string, string>;
    initialValues: Record<string, string>;
    readFromMetadata?: string[];
    writeMetadata?: string[];
    writeMetadataRenditions?: string[];
    workspace: Workspace | string;
    policy: AttributePolicy | string | null;
    lastErrors?: LastError[];
    target: AssetType;
    position?: number;
    translations?: Record<string, Translations>;
}

export type AttributeDefinitionOrBuiltIn =
    | AttributeDefinition
    | BuiltInAttribute;

export interface AttributePolicy extends HydraObject, Entity {
    name: string;
    public: boolean;
    editable: boolean;
    workspace: Workspace | string;
}

export type AttributeOrigin = 'human' | 'machine' | 'fallback' | 'initial';

export interface Attribute extends Partial<HydraObject>, Entity {
    definition: AttributeDefinition;
    origin: AttributeOrigin;
    multiple: boolean;
    invalid?: boolean;
    originVendor?: string;
    locale?: string;
    originUserId?: string;
    value: any;
    highlight?: any;
    assetAnnotations?: AssetAnnotation[];
    position?: number;
}

export type LastError = {
    date: string;
    message: string;
    code: number;
    file: string;
    line: number;
};

export enum AttributeEntityStatus {
    Approved = 0,
    Pending = 1,
    Rejected = 2,
}

export interface AttributeEntity extends HydraObject, Entity {
    type?: string;
    list?: EntityList | string;
    locale?: string;
    value: string;
    emoji?: string;
    color?: string;
    translations: Translations;
    synonyms?: Record<string, string[]>;
    createdAt: string;
    updatedAt: string;
    status: AttributeEntityStatus;
}

export interface EntityList extends HydraObject, Entity {
    name: string;
    definitions?: AttributeDefinition[];
    allowNewValues?: boolean;
    approveNewValues?: boolean;
    withTranslations?: boolean;
    withSynonyms?: boolean;
    withEmojis?: boolean;
    withColors?: boolean;
    workspace?: Workspace | string;
    createdAt: string;
    updatedAt: string;
}

export interface FieldType extends HydraObject {
    name: string;
    displayName: string;
}

// ---------------------------------------------------------------------------
// Assets

export type AssetAnnotation = {
    id?: string;
    type: string;
    name?: string;
    editable?: boolean;
    [prop: string]: unknown;
};

export type GroupValue = {
    name: string;
    key: string | null;
    values: unknown[];
    type: AttributeType;
};

export interface AssetAttachment extends Entity {
    asset?: Asset;
    attachment: Asset;
    name?: string;
    priority: number;
    updatedAt: string;
    createdAt: string;
}

export interface Thread extends Entity {
    key: string;
    createdAt: string;
}

export interface Asset
    extends
        WithCapabilities<{
            editAttributes: boolean;
            share: boolean;
        }>,
        Entity {
    name?: string;
    nameHighlight: string | null;
    description?: string;
    privacy: Privacy;
    tags?: Tag[];
    owner?: User;
    threadKey: string;
    thread?: Thread;
    workspace: Workspace;
    attributes: Attribute[];
    attachments: AssetAttachment[];
    referenceCollection?: Collection;
    collections?: Collection[];
    main: AssetRendition | null;
    preview: AssetRendition | null;
    source?: ApiFile;
    thumbnail: AssetRendition | null;
    animatedThumbnail: AssetRendition | null;
    createdAt: string;
    updatedAt: string;
    editedAt: string;
    attributesEditedAt: string;
    groupValue?: GroupValue;
    topicSubscriptions?: string[];
    storyCollection?: Collection;
    deleted?: boolean;
    trackingId?: string;
    status: AssetStatus;
    resolvedTrackingId?: string;
    webUrl?: string;
}

export interface AssetFileVersion extends Entity {
    asset: Asset;
    file: ApiFile;
    name: string;
    createdAt: string;
}

export type ESDocumentState = {
    synced: boolean;
    data: object;
};

export type DuplicateAsset = {
    asset: Asset;
    analyzers: string[];
};

export type MatomoMediaMetrics = Record<string, string | number>;

// ---------------------------------------------------------------------------
// Baskets, saved searches, profiles, pages

export interface Basket extends WithCapabilities<{share: boolean}>, Entity {
    name: string;
    nameHighlight?: string;
    description?: string;
    descriptionHighlight?: string;
    assetCount?: number;
    createdAt: string;
    updatedAt: string;
    isArchived: boolean;
    owner?: User;
}

export interface BasketAsset extends Entity {
    asset: Asset;
    context?: unknown;
    nameHighlight?: string;
    position: number;
    createdAt: string;
    owner?: User;
    assetAnnotations?: AssetAnnotation[];
}

export type SortBy = {
    /** attribute slug */
    a: string;
    /** 0 = ASC, 1 = DESC */
    w: 0 | 1;
    /** grouped in UI */
    g: boolean;
};

export type AQLQuery = {
    id: string;
    query: string;
    disabled?: boolean;
    inversed?: boolean;
};

export type SavedSearchData = {
    query?: string;
    conditions: AQLQuery[];
    sortBy: SortBy[];
};

export enum SavedSearchPrivacy {
    Secret = 0,
    Private = 1,
    Public = 2,
}

export interface SavedSearch extends WithCapabilities, Entity {
    name: string;
    privacy?: SavedSearchPrivacy;
    createdAt: string;
    updatedAt: string;
    data: SavedSearchData;
    owner?: User;
}

export enum ProfileItemSection {
    Attributes = 0,
    Facets = 1,
    Grid = 2,
}

export enum ProfileItemType {
    Definition = 0,
    BuiltIn = 1,
    Divider = 2,
    Spacer = 3,
}

export type GridRegion = 'over' | 'below';
export type GridAnchor =
    | 'l'
    | 'c'
    | 'r'
    | 'tc'
    | 'ml'
    | 'cc'
    | 'mr'
    | 'bl'
    | 'bc';

export type ItemPlacement = {
    region: GridRegion;
    anchor: GridAnchor;
    order?: number;
};

export enum ProfileItemVariant {
    Rich = 'rich',
    Chip = 'chip',
    Text = 'text',
}

export enum ProfileItemSize {
    Small = 'small',
    Medium = 'medium',
    Large = 'large',
}

export type ProfileItem = {
    id: string;
    section: ProfileItemSection;
    type: ProfileItemType;
    key?: string;
    definition?: string;
    displayEmpty?: boolean;
    format?: string;
    placement?: ItemPlacement;
    variant?: ProfileItemVariant;
    color?: string;
    size?: ProfileItemSize;
    showLabel?: boolean;
    showIcon?: boolean;
};

export interface DisplayProfile extends WithCapabilities, Entity {
    name: string;
    description?: string;
    items?: ProfileItem[];
    exclusive?: boolean;
    public?: boolean;
    createdAt: string;
    updatedAt: string;
    data?: Record<string, unknown>;
    owner?: User;
}

export interface CmsPage extends WithCapabilities, Entity {
    title: string;
    description?: string;
    slug: string;
    enabled?: boolean;
    public?: boolean;
    createdAt: string;
    updatedAt: string;
    data?: unknown;
    owner?: User;
}

// ---------------------------------------------------------------------------
// Sharing

export type ShareAlternateUrl = {
    name: string;
    url: string;
    type?: string;
};

export interface Share extends Entity {
    name?: string;
    asset: Asset;
    token: string;
    startsAt?: string | null;
    expiresAt?: string | null;
    updatedAt: string;
    createdAt: string;
    alternateUrls: ShareAlternateUrl[];
}

// ---------------------------------------------------------------------------
// Discussion

export type MessageAttachment = {
    type: string;
    content: string;
};

export interface ThreadMessage extends Entity {
    content: string;
    attachments?: MessageAttachment[];
    author: User;
    createdAt: string;
    updatedAt: string;
    acknowledged?: boolean;
    capabilities: {
        delete: boolean;
        edit: boolean;
    };
}

// ---------------------------------------------------------------------------
// Permissions

export type Ace = Entity & {
    mask: number;
    userId: string | null;
    userType: UserType;
    user?: User | null;
    group?: Group | null;
    objectType?: string;
    objectId?: string | null;
    wildcard?: boolean;
    metadata?: number[];
};

export interface TagFilterRule extends HydraObject, Entity {
    userId?: string;
    username?: string;
    groupId?: string;
    groupName?: string;
    workspaceId?: string;
    collectionId?: string;
    include: Tag[];
    exclude: Tag[];
}

export interface AttributeFilterRule extends HydraObject, Entity {
    users?: (User | string)[];
    groups?: (Group | string)[];
    workspace?: Workspace | string;
    condition: string;
}

// ---------------------------------------------------------------------------
// Export, templates, tasks, workflows, integrations

export enum ExportStatus {
    Pending = 0,
    InProgress = 1,
    Ready = 2,
    Failed = 3,
}

export type AssetExport = Entity & {
    status: ExportStatus;
    error?: string;
    progress?: number;
    downloadUrl?: string;
};

export type AssetDataTemplate = Entity & {
    name: string;
    workspace: string;
    collection?: string;
    includeCollectionChildren: boolean;
    attributes?: Attribute[] | AttributeBatchAction[];
    privacy?: Privacy | null;
    public: boolean;
    tags?: Tag[];
    assetName?: string;
};

export enum OperationTaskStatus {
    Pending = 0,
    InProgress = 1,
    Completed = 2,
    Failed = 3,
    Cancelled = 4,
}

export type OperationTask = Entity & {
    task: string;
    payload: Record<string, unknown>;
    owner: User | string;
    status: OperationTaskStatus;
    remaining?: string;
    startedAt?: string;
    progression?: number;
    endedAt?: string;
    output?: string;
    itemTotal?: string;
    progress?: string;
    createdAt: string;
};

export enum WorkflowStatus {
    Started = 0,
    Success = 1,
    Failure = 2,
    Cancelled = 3,
}

export type WorkflowJob = Entity & {
    name: string;
    status: number;
    startedAt?: string;
    completedAt?: string;
    errors?: string[];
    needs?: string[];
    stage?: number;
};

export type Workflow = Entity & {
    name: string;
    status: WorkflowStatus;
    startedAt?: string;
    completedAt?: string;
    jobs?: WorkflowJob[];
    stages?: {jobs: WorkflowJob[]}[];
    event?: {name: string; inputs?: Record<string, unknown>};
};

export type IntegrationData = Entity & {
    object?: object;
    keyId: string | null;
    name: string;
    value: unknown;
};

export type IntegrationToken = Entity & {
    userId: string;
    expired: boolean;
    expiresAt: string;
    createdAt: string;
};

export interface WorkspaceIntegration
    extends WithCapabilities<{use: boolean; interact: boolean}>, Entity {
    title?: string;
    name: string;
    public: boolean;
    enabled: boolean;
    integration: string;
    integrationName?: string;
    data?: IntegrationData[];
    config: object;
    configYaml: string;
    configInfo?: {label: string; description: string; value?: string}[];
    tokens?: IntegrationToken[];
    workspace: Workspace | string;
    owner?: User;
    if?: string;
    needs?: string[];
    lastErrors?: LastError[];
    supported?: boolean;
}

export type IntegrationType = {
    id: string;
    displayName: string;
    name: string;
    reference: string;
    references: {
        name: string;
        description?: string | null;
        reference: string;
    }[];
};

export type Locale = {
    id: string;
    language: string;
    region: string;
    script: string;
    name: string;
    nativeName: string;
};

export type SearchSuggestion = {
    id: string;
    name: string;
    hl: string;
    t: 'collection' | 'asset' | 'workspace';
    tName: string;
    tId?: string;
};

// ---------------------------------------------------------------------------
// Facets

export enum FacetType {
    Text = 'text',
    Boolean = 'boolean',
    DateRange = 'date_range',
    GeoDistance = 'geo_distance',
    Entity = 'entity',
}

export type BucketValue = string | number | boolean;
export type LabelledBucketValue = {
    label: string;
    value: BucketValue;
    item?: Record<string, unknown>;
};

export type FacetBucket = {
    key: BucketValue | LabelledBucketValue;
    doc_count: number;
    from?: number;
    to?: number;
};

export type Facet = {
    meta: {
        displayName: string;
        locale?: string;
        widget?: FacetType;
        type?: AttributeType;
        sortable: boolean;
        position?: [number, number];
    };
    buckets: FacetBucket[];
    doc_count_error_upper_bound?: number;
    sum_other_doc_count?: number;
    missing_count?: number;
    interval?: string;
};

export type Facets = Record<string, Facet>;

export type ESDebug = {
    query: object;
    esQueryTime: number;
    totalResponseTime: number;
};
