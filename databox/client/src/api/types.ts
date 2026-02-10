export enum AttributeBatchActionEnum {
    Set = 'set',
    Replace = 'replace',
    Add = 'add',
    Delete = 'delete',
}

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
    Story = 'story',
    Color = 'color',
    Date = 'date',
    DateTime = 'date_time',
    Entity = 'entity',
    GeoPoint = 'geo_point',
    Html = 'html',
    Id = 'id',
    Ip = 'ip',
    Json = 'json',
    Keyword = 'keyword',
    Number = 'number',
    Privacy = 'privacy',
    Tag = 'tag',
    Text = 'text',
    Textarea = 'textarea',
    WebVtt = 'web_vtt',
    Workspace = 'workspace',
    Rendition = 'rendition',
    User = 'user',
}

export enum Entity {
    Asset = 'assets',
    Attachment = 'attachments',
    Collection = 'collections',
    Workspace = 'workspaces',
}
