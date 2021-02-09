export interface Asset {
    id: string;
    title: string;
    description?: string;
    privacy: number;
    tags: Tag[];
    collections: Collection[];
}

export interface Tag {
    id: string;
    name: string;
}

export interface User {
    id: string;
    username: string;
}

export interface Collection {
    id: string;
    title: string;
    children?: Collection[];
    workspace: Workspace;
    capabilities: {
        canEdit: boolean,
        canDelete: boolean,
    };
}

export interface Workspace {
    id: string;
    name: string;
    collections: Collection[];
    capabilities: {
        canEdit: boolean,
        canDelete: boolean,
    };
}

export interface Ace {
    id: string;
    userType: string;
    userId: string;
    mask: number;
}
