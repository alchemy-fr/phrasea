import {ApiHydraObjectResponse} from "./api/hydra";

type AlternateUrl = {
    type: string;
    url: string;
    label?: string;
}

export interface File {
    id: string;
    url?: string;
    alternateUrls: AlternateUrl[];
    size: number;
}

export interface Asset extends IPermissions {
    id: string;
    title: string;
    description?: string;
    privacy: number;
    tags: Tag[];
    workspace: Workspace;
    collections: Collection[];
    original: File | null;
    preview: File | null;
    thumbnail: File | null;
    thumbnailActive: File | null;
}

export interface IPermissions extends ApiHydraObjectResponse {
    capabilities: {
        canEdit: boolean,
        canDelete: boolean,
        canEditPermissions: boolean,
    };
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
}

export interface User {
    id: string;
    username: string;
}

export interface Group {
    id: string;
    name: string;
}

export interface Collection extends IPermissions {
    id: string;
    title: string;
    children?: Collection[];
    workspace: Workspace;
    privacy: number;
}

export interface Workspace extends IPermissions {
    id: string;
    name: string;
    collections: Collection[];
}

export interface Ace {
    id: string;
    userType: string;
    userId: string;
    mask: number;
}
