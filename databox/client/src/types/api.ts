/**
 * Databox API contracts (API Platform / Hydra).
 * These types mirror the JSON payloads returned by `databox/api`.
 */

import type {ConfigObjectSchema} from '@/features/integrations/config/schema';

export interface HydraObject {
    '@id': string;
    '@type': string;
}

export interface Entity {
    id: string;
}

export type HydraCollection<T, E extends object = object> = {
    totalItems: number;
    member: T[];
    view?: {
        first?: string;
        previous?: string;
        next?: string;
        last?: string;
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
    firstName?: string | null;
    lastName?: string | null;
    displayName?: string | null;
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
    /** Client-side type of the `@family` built-in (a keyword for the API) */
    FileFamily = 'file_family',
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
    /** Client-side type of the `@similar` built-in: an asset ID */
    Asset = 'asset',
    Tag = 'tag',
    Text = 'text',
    Textarea = 'textarea',
    User = 'user',
    WebVtt = 'web_vtt',
    Workspace = 'workspace',
}

export enum EntityName {
    Asset = 'assets',
    AssetImport = 'asset-imports',
    AssetPolicy = 'asset-policies',
    Attachment = 'attachments',
    Collection = 'collections',
    Workspace = 'workspaces',
    SavedSearch = 'saved-searches',
    SavedSearchAutomation = 'saved-search-automations',
    AutomationRun = 'automation-runs',
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
    AuditLog = 'audit-logs',
    WorkspaceSecret = 'workspace-secrets',
    WorkspaceEnv = 'workspace-envs',
    SystemEnv = 'system-envs',
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
    /** Shapes drawn over the asset for the value; null keeps the stored ones */
    annotations?: AssetAnnotation[] | null;
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
    /** Terms & Conditions to accept before accessing the content */
    terms?: WorkspaceTerms | null;
    termsUnsigned?: boolean;
    /** Custom logo, the service one when none */
    logo?: string | null;
    /** Its assets can be searched by visual similarity (`@similar`) */
    similarity?: boolean;
}

export type WorkspaceTerms = {
    /** Resolved for the current user's locale */
    text: string | null;
    /** Untranslated source text, to edit */
    rawText?: string | null;
    translations?: Record<string, string> | null;
    version: number | null;
    signed: boolean | null;
    attachToExports: boolean;
    pdfUrl?: string | null;
};

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
    /** Values may carry shapes drawn over the asset */
    annotationsEnabled?: boolean;
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
    AttributeDefinition | BuiltInAttribute;

export interface AttributePolicy extends HydraObject, Entity {
    name: string;
    public: boolean;
    editable: boolean;
    workspace: Workspace | string;
}

export type AttributeOrigin = 'human' | 'machine' | 'fallback' | 'initial';

export interface Attribute extends Partial<HydraObject>, Entity {
    definition: AttributeDefinition;
    /** The asset, as an IRI or an object, on the collections of a definition */
    asset?: Asset | string;
    origin: AttributeOrigin;
    confidence?: number;
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
    /** Tells apart the values sharing the same name (homonyms) */
    note?: string | null;
    emoji?: string | null;
    color?: string | null;
    /** Photo: an optimized image URL, or a data URI for an SVG */
    image?: string | null;
    /** Light version of the photo (falls back to the image until it is processed) */
    thumbnail?: string | null;
    translations: Translations;
    synonyms?: Record<string, string[]>;
    createdAt: string;
    updatedAt: string;
    status: AttributeEntityStatus;
    /** Attribute values referencing the entity, with the `usage` parameter */
    usage?: {attributes: number; humanAttributes: number};
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
    withImages?: boolean;
    workspace?: Workspace | string;
    createdAt: string;
    updatedAt: string;
    /** Served with the list itself, not when nested in another resource */
    capabilities?: Capabilities<{editValues: boolean}>;
}

export interface FieldType extends HydraObject {
    name: string;
    displayName: string;
}

// ---------------------------------------------------------------------------
// Assets

/**
 * A shape drawn over an asset (see `features/annotations/types`): relative
 * coordinates (0..1), `page` for documents, `c` / `s` for color and size.
 */
export type AssetAnnotation = {
    id?: string;
    type: string;
    name?: string;
    page?: number;
    /**
     * Time in seconds of the frame of a video the shape is drawn on, the
     * instant of a cue
     */
    t?: number;
    /**
     * Client only: tooltip of the label of a read only shape, never sent to
     * the API
     */
    title?: string;
    /**
     * Client only: where a read only shape of the asset comes from (its
     * attribute value and definition, its position among the shapes of that
     * value), never sent to the API
     */
    source?: {attributeId: string; definitionId: string; index: number};
    [prop: string]: any;
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
    /**
     * Client side only: the basket item this asset is displayed for. An asset
     * can be in a basket several times, each item is selected on its own.
     */
    basketItemId?: string;
    /**
     * Client side only: the clip of the basket item this asset is displayed
     * for, applied when the asset is played or shown.
     */
    basketClip?: MediaClip;
    /** Region of interest: applied by default when the asset is shown, published or exported */
    roi?: MediaClip | null;
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

/** Bounding box normalized to the image size, origin top-left */
export type FaceBox = {x: number; y: number; w: number; h: number};

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

/** Part of a video or an audio, in seconds */
export type ClipTimeRange = {s: number; e: number};

/** Rectangle of an image or a video, relative to its displayed size (0..1) */
export type ClipCrop = {x: number; y: number; w: number; h: number};

/**
 * Time ranges and/or crop of a media: region of interest of an asset, or
 * extract of a basket item (which replaces the region of interest of its
 * asset). The file is not cut: the clip is applied on display, and when the
 * asset is published or exported (the ranges are then concatenated). The
 * ranges are sorted and do not overlap.
 */
export type MediaClip = {
    timeRanges?: ClipTimeRange[];
    crop?: ClipCrop;
};

export interface BasketAsset extends Entity {
    asset: Asset;
    context?: unknown;
    clip?: MediaClip | null;
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

export interface SavedSearch
    extends
        WithCapabilities<{manageAutomations: boolean; publish: boolean}>,
        Entity {
    name: string;
    privacy?: SavedSearchPrivacy;
    createdAt: string;
    updatedAt: string;
    data: SavedSearchData;
    owner?: User;
}

/**
 * Capabilities of the current user granted at the instance level (outside any
 * workspace), see `features/permissions/instancePermissions.ts`.
 */
export type InstanceCapabilities = {
    createWorkspace: boolean;
    shareProfile: boolean;
    shareBasket: boolean;
    publishSavedSearch: boolean;
};

export enum AutomationScope {
    New = 'new',
    All = 'all',
}

export enum AutomationRunStatus {
    Pending = 'pending',
    Running = 'running',
    Success = 'success',
    Partial = 'partial',
    Failed = 'failed',
    Skipped = 'skipped',
    Cancelled = 'cancelled',
}

export type AutomationTrigger = 'manual' | 'schedule';

/** Configuration of an action, secrets masked (`null` + `<field>Set`) */
export type AutomationActionConfig = Record<string, any>;

export type AutomationAction = {
    type: string;
    config: AutomationActionConfig;
};

export interface SavedSearchAutomation extends HydraObject, Entity {
    savedSearchId: string;
    /** Workspace the results of the saved search are limited to */
    workspaceId?: string | null;
    name: string;
    enabled: boolean;
    /** Cron expression, null when run on demand only */
    schedule?: string | null;
    timezone: string;
    scope: AutomationScope;
    /** Maximum number of assets handed to the actions per run */
    maxAssetsPerRun: number;
    actions: AutomationAction[];
    nextRunAt?: string | null;
    lastRunAt?: string | null;
    lastRunStatus?: AutomationRunStatus | null;
    consecutiveFailures: number;
    createdBy?: User;
    createdAt: string;
    updatedAt: string;
    capabilities: {edit: boolean; delete: boolean; run: boolean};
}

export type AutomationActionResult = {
    type: string;
    status: 'success' | 'partial' | 'failed';
    processed: number;
    failed: number;
    skipped: number;
    errors: {assetId?: string; message: string}[];
};

export interface AutomationRun extends HydraObject, Entity {
    automationId: string;
    trigger: AutomationTrigger;
    dryRun: boolean;
    initiator?: User;
    status: AutomationRunStatus;
    startedAt?: string | null;
    endedAt?: string | null;
    matchedCount: number;
    newCount: number;
    processedCount: number;
    failedCount: number;
    /** Eligible assets were left out by the limit of assets per run (dry run: would be) */
    limitReached: boolean;
    actionResults: AutomationActionResult[];
    error?: string | null;
    createdAt: string;
}

export enum ProfileItemSection {
    Attributes = 0,
    Facets = 1,
    Grid = 2,
    Filters = 3,
}

export enum ProfileItemType {
    Definition = 0,
    BuiltIn = 1,
    Divider = 2,
    Spacer = 3,
}

export type GridRegion = 'over' | 'below';
export type GridAnchor =
    'l' | 'c' | 'r' | 'tc' | 'ml' | 'cc' | 'mr' | 'bl' | 'bc';

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

/** How a pinned boolean search filter picks its value */
export enum FilterWidget {
    Select = 'select',
    /** Radio buttons in a popover */
    Radio = 'radio',
    /** Radio buttons laid out in the search bar */
    Inline = 'inline',
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
    widget?: FilterWidget | '';
    /** Labels of the values of a boolean filter (Yes / No by default) */
    trueLabel?: string | null;
    falseLabel?: string | null;
    /** A boolean filter offers "false" first */
    reversed?: boolean;
    /** AQL condition a search filter applies by default */
    defaultQuery?: string | null;
};

export interface DisplayProfile
    extends WithCapabilities<{share: boolean}>, Entity {
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

// ---------------------------------------------------------------------------
// Sharing

/**
 * A rendition of a shared asset, downloadable through the share. Several
 * renditions may point to the same file: `id` identifies the rendition.
 */
export type ShareAlternateUrl = {
    /** The rendition */
    id?: string | null;
    definitionId?: string | null;
    name: string;
    /** Translated name of the rendition definition */
    displayName?: string | null;
    url: string;
    type?: string | null;
    size?: number | null;
    /** The shared asset this URL belongs to */
    assetId?: string | null;
};

/** An attachment of a shared asset, downloadable from the public page */
export type ShareAttachment = {
    id: string;
    name: string | null;
    assetId: string;
    url: string;
    type: string | null;
    size: number | null;
};

/** Terms & Conditions of the shared workspace, to accept before viewing */
export type ShareTerms = {
    text: string | null;
    version: number;
    workspaceName: string;
    pdfUrl?: string | null;
};

export interface Share extends Entity {
    name?: string;
    /** Every shared asset belongs to the same workspace */
    assets: Asset[];
    /**
     * The rendition definitions the link delivers when `renditionsRestricted`,
     * otherwise every rendition of a public policy.
     */
    renditionDefinitions?: Pick<RenditionDefinition, 'id'>[];
    /** Restricted to `renditionDefinitions`, none if they were all deleted */
    renditionsRestricted?: boolean;
    token: string;
    startsAt?: string | null;
    expiresAt?: string | null;
    updatedAt: string;
    createdAt: string;
    alternateUrls: ShareAlternateUrl[];
    /** Public share only */
    attachments?: ShareAttachment[];
    /** Public share only */
    terms?: ShareTerms | null;
    /** Public share only: logo of the workspace */
    logo?: string | null;
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

/**
 * Assets of the workspace are only visible to the targeted users and groups
 * (everyone when there is no target) when they match the AQL condition.
 */
export interface AttributeFilterRule extends HydraObject, Entity {
    users: {id: string; name: string}[];
    groups: {id: string; name: string}[];
    workspaceId: string;
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

export enum JobStatus {
    Triggered = 0,
    Success = 1,
    Failure = 2,
    Skipped = 3,
    Running = 4,
    Error = 5,
    Cancelled = 6,
}

/**
 * A job of a workflow run, as dumped by `JsonWorkflowDumper`: the planned job
 * merged with its last state, when it ran.
 */
export type WorkflowJob = {
    jobId: string;
    name: string;
    needs?: string[];
    if?: string;
    with?: Record<string, unknown>;
    disabled?: boolean;
    disabledReason?: string;
    /** Last state of the job, absent until it is triggered */
    stateId?: string;
    status?: JobStatus;
    number?: number;
    inputs?: Record<string, unknown> | unknown[];
    outputs?: Record<string, unknown> | unknown[];
    triggeredAt?: string;
    startedAt?: string;
    endedAt?: string;
    /** Formatted by the API (e.g. `1.234s`, `02m05s`), `-` while running */
    duration?: string;
    errors?: string[];
    /** Why the job was skipped */
    skipReason?: string;
};

export type WorkflowStage = {
    stage: number;
    jobs: WorkflowJob[];
};

/** Item of the workflow collection */
export type Workflow = Entity & {
    /** `<workflow>:<workspace id>` for the workflows run on assets */
    name: string;
    status: WorkflowStatus;
    /** Rank among the runs of the same workflow on its asset, from 1 */
    number?: number | null;
    eventName?: string | null;
    startedAt?: string;
    endedAt?: string | null;
    /** Formatted by the API, `-` while running */
    duration?: string | null;
    assetId?: string | null;
    assetName?: string | null;
};

/** A workflow run with its plan (GET /workflows/{id}, rerun, cancel) */
export type WorkflowDetail = {
    id: string;
    name: string;
    status: WorkflowStatus;
    startedAt: string;
    endedAt?: string | null;
    /** Formatted by the API, `-` while running */
    duration?: string;
    stages: WorkflowStage[];
    context?: Record<string, unknown> | unknown[] | null;
    outputs?: Record<string, unknown> | unknown[] | null;
    event?: {name: string; inputs?: Record<string, unknown> | unknown[]};
    number?: number | null;
    /** The asset the workflow ran on */
    asset?: {id: string; name?: string | null} | null;
};

export enum AuditLogActorType {
    User = 'user',
    OAuthClient = 'oauth_client',
    System = 'system',
}

/** A change of an attribute in an `asset_attribute_changed` entry */
export type AuditLogAttributeChange = {
    definitionId: string;
    definitionName?: string | null;
    locale?: string | null;
    op: 'set' | 'add' | 'delete' | 'replace';
    old: string[];
    new: string[];
};

/** An entry of the audit log (admins) */
export type AuditLog = Entity & {
    date: string;
    /** Action key, e.g. `asset_moved` */
    action: string;
    actorType: AuditLogActorType;
    /** The acting user, with the username they had then */
    user?: {id: string; username?: string | null} | null;
    impersonatorId?: string | null;
    clientId?: string | null;
    ip?: string | null;
    /** Object type key, e.g. `asset`, `saved_search` */
    objectType?: string | null;
    objectId?: string | null;
    /** The title or name of the object when the action was logged */
    objectLabel?: string | null;
    workspaceId?: string | null;
    /** Shared by an action and its asynchronous effects */
    correlationId?: string | null;
    data: Record<string, unknown> | unknown[];
    /** `uri`, `_route`, `source` (api, admin, console, worker, automation, integration), `command` */
    meta: Record<string, unknown> | unknown[];
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
    name?: string | null;
    public: boolean;
    enabled: boolean;
    integration: string;
    integrationName?: string;
    data?: IntegrationData[];
    config: object;
    configYaml: string;
    /** The stored configuration, as `configYaml` but structured (editors only) */
    configValues?: Record<string, unknown>;
    configInfo?: {label: string; description: string; value?: string}[];
    tokens?: IntegrationToken[];
    /** Null for an instance-wide integration */
    workspace: Workspace | string | null;
    owner?: User;
    if?: string;
    needs?: string[];
    lastErrors?: LastError[];
    supported?: boolean;
}

export type IntegrationCategory =
    | 'processing'
    | 'ai'
    | 'editor'
    | 'publication'
    | 'ingest'
    | 'automation'
    | 'other';

export type IntegrationType = {
    id: string;
    displayName: string;
    name: string;
    description: string;
    /** The first one is the main category */
    categories: IntegrationCategory[];
    /** False when it can also be set up on the whole instance */
    requiresWorkspace: boolean;
    /** "workflow" and/or the client contexts ("asset-view", "basket") */
    features: string[];
    reference: string;
    references: {
        name: string;
        description?: string | null;
        reference: string;
    }[];
    /** The configuration tree, from which the form is generated */
    schema: ConfigObjectSchema;
};

/** A Keycloak OAuth client an integration can authorize its users with */
export type OAuthClient = {
    /** The Keycloak ID (not the client ID) */
    id: string;
    clientId: string;
    name?: string;
    description?: string;
    redirectUris: string[];
    /** The redirect URIs of the client allow the one of the integration */
    redirectUriAllowed: boolean;
    /** Expose accepts the access tokens of the client */
    acceptedByExpose: boolean;
};

/**
 * A value referenced as `${NAME}` by the configuration of the integrations of
 * its workspace. Write only: its value is never returned.
 */
export type WorkspaceSecret = {
    id: string;
    name: string;
    /** IRI of the workspace, missing for an instance secret */
    workspace?: string | null;
    createdAt: string;
    updatedAt: string;
};

/** A plain value, referenced like a secret but stored and returned in clear */
export type WorkspaceEnv = {
    id: string;
    name: string;
    value: string;
    /** IRI of the workspace, missing for an instance variable */
    workspace?: string | null;
    createdAt: string;
    updatedAt: string;
};

/**
 * A `PWI_<NAME>` environment variable of the instance, that a reference
 * resolves to when no secret nor variable of its scope has this name. Named
 * without the prefix; its value is never returned.
 */
export type SystemEnv = {
    name: string;
};

export type MetadataTag = {
    /** Namespace (e.g. "IPTC") or namespaced tag (e.g. "IPTC:Keywords") */
    id: string;
    namespace: string;
    /** Null for a namespace entry */
    name?: string | null;
    description?: string | null;
    writable?: boolean | null;
    multi?: boolean | null;
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
    /** Kind of the suggestion, or the id of the attribute definition of a value */
    t: 'collection' | 'asset' | 'workspace' | string;
    tName: string;
    tId?: string;
    /** Locale of an attribute value (translatable definitions) */
    locale?: string;
    /** Id of the entity behind an entity attribute value */
    entityId?: string;
};

// ---------------------------------------------------------------------------
// Asset imports (JSON / NDJSON instructions file)

export type AssetImportFormat = 'json' | 'ndjson';

export type AssetImportStatus =
    'pending' | 'dispatching' | 'processing' | 'completed' | 'failed';

export type AssetImport = Entity & {
    workspaceId: string;
    collectionId?: string;
    collectionTitle?: string;
    /** Set when the import was triggered by an integration (webhook) */
    integrationId?: string;
    integrationTitle?: string;
    owner?: Pick<User, 'id' | 'username'>;
    format: AssetImportFormat;
    privacy?: Privacy;
    originalName?: string;
    status: AssetImportStatus;
    /** Unknown until the whole file has been read */
    total?: number;
    created: number;
    skipped: number;
    failed: number;
    /** Fatal error which stopped the import (invalid JSON…) */
    error?: string;
    endedAt?: string;
    createdAt: string;
    updatedAt: string;
};

export type AssetImportError = Entity & {
    /** Line of the NDJSON file, or position (from 1) in the JSON array */
    line: number;
    key?: string;
    message: string;
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
