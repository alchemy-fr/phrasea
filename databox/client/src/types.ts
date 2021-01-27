export interface Asset {
    id: string;
    title: string;
    description?: string;
    public: boolean;
    tags: Tag[];
    collections: Collection[];
}

export interface Tag {
    id: string;
    name: string;
}

export interface Collection {
    id: string;
    title: string;
    children?: Collection[];
    capabilities: {
        canEdit: boolean,
        canDelete: boolean,
    };
}
