type AlternateUrl = {
    type: string;
    url: string;
};

type Source = {
    url: string;
    isPrivate?: boolean;
    alternateUrls?: AlternateUrl[];
    importFile?: boolean;
    type?: string;
};

export type AssetInput = {
    sourceFile?: Source;
    key?: string;
    title?: string;
    collection?: string;
    workspace?: string;
    workspaceId?: string;
    attributes?: AttributeInput[];
    renditions?: RenditionInput[];
    generateRenditions?: boolean;
};

export type CollectionInput = {
    workspace?: string;
    workspaceId?: string;
    title?: string;
    parent?: string;
    key?: string;
};

export type AttributeInput = ({value: any} | {values: any[]}) & {
    definition: string;
    origin?: string;
    originVendor?: string;
    originUserId?: string;
    originVendorContext?: string;
    coordinates?: string;
    status?: string;
    confidence?: number;
};

export type RenditionInput = {
    definitionId?: string;
    name?: string;
    source?: Source;
};

export type RenditionClass = {
    id: string;
    name: string;
};

export type AttributeDefinition = {
    id: string;
    multiple: boolean;
    key?: string | undefined;
    name: string;
    editable: boolean;
    fieldType: string;
    workspace: string;
    class: string;
};

export type AttributeClass = {
    ['@id']: string;
    id: string;
    key?: string | undefined;
    name: string;
    editable: boolean;
    public: boolean;
    workspace: string;
};
