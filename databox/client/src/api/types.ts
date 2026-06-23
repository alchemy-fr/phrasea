import {User} from '../types.ts';

export enum AttributeBatchActionEnum {
    Set = 'set',
    Replace = 'replace',
    Add = 'add',
    Delete = 'delete',
}

export type PaginationParams = {
    nextUrl?: string;
};

export enum OperationTaskStatus {
    Pending = 0,
    InProgress = 1,
    Completed = 2,
    Failed = 3,
    Cancelled = 4,
}

export type OperationTask = {
    id: string;
    task: string;
    payload: Record<string, any>;
    owner: User | string;
    status: OperationTaskStatus;
    remaining?: string;
    startedAt: string;
    progression?: number;
    endedAt?: string;
    output?: string;
    itemTotal?: string;
    progress?: string;
    createdAt: string;
};

export type AttributeBatchAction = {
    action?: AttributeBatchActionEnum | undefined;
    id?: string | undefined;
    ids?: string[] | undefined;
    assets?: string[] | undefined;
    value?: any | undefined;
    definitionId?: string | undefined;
    locale?: string | undefined;
    position?: number | undefined;
};

export enum AttributeType {
    Boolean = 'boolean',
    Code = 'code',
    CollectionPath = 'collection_path',
    Color = 'color',
    Date = 'date',
    DateTime = 'date_time',
    Duration = 'duration',
    Entity = 'entity',
    GeoPoint = 'geo_point',
    Html = 'html',
    Id = 'id',
    Ip = 'ip',
    Json = 'json',
    Keyword = 'keyword',
    Number = 'number',
    Privacy = 'privacy',
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
    Entity = 'attribute-entities',
    User = 'users',
    Tag = 'tags',
    AssetDataTemplate = 'asset-data-templates',
    OperationTask = 'operation-tasks',
}
