import {DataboxAttributeType} from '../handlers/phraseanet/shared';

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
    tags?: TagInput[];
    renditions?: RenditionInput[];
    generateRenditions?: boolean;
    isStory?: boolean;
};

export type AssetCopyInput = {
    destination: string;
    ids: string[];
    byReference: boolean;
    withAttributes?: boolean;
    withTags?: boolean;
};

export type CollectionInput = {
    workspace?: string;
    workspaceId?: string;
    title?: string;
    parent?: string;
    key?: string;
    privacy?: number;
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
    locale: string;
    position: number;
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

type Labels = Record<string, any>;

export type AttributeDefinition = {
    id: string;
    multiple: boolean;
    key?: string | undefined;
    name: string;
    editable: boolean;
    fieldType: DataboxAttributeType;
    workspace: string;
    class: string;
    translatable: boolean;
    labels?: Labels | undefined;
};

export type Tag = {
    workspace: string;
    id: string;
    name: string;
    color?: string | undefined;
};

export type TagInput = string;

export type AttributeClass = {
    ['@id']: string;
    id: string;
    key?: string | undefined;
    name: string;
    editable: boolean;
    public: boolean;
    workspace: string;
};

export type CollectionOutput = {
    id: string;
}

export type AssetOutput = {
    id: string;
}

export type StoryAssetOutput = {
    id: string;
    storyCollection: CollectionOutput;
}
