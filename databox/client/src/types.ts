export interface Asset extends IPermissions {
    id: string;
    title: string;
    description?: string;
    privacy: number;
    tags: Tag[];
    workspace: Workspace;
    collections: Collection[];
}

export interface IPermissions {
    capabilities: {
        canEdit: boolean,
        canDelete: boolean,
        canEditPermissions: boolean,
    };
}

export interface Tag {
    id: string;
    name: string;
}

export interface User {
    id: string;
    username: string;
}

export interface Collection extends IPermissions {
    id: string;
    title: string;
    children?: Collection[];
    workspace: Workspace;
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
