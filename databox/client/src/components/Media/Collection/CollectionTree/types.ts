export enum EntityType {
    Workspace = 1,
    Collection = 2,
}

export type WorkspaceOrCollectionTreeItem = {
    id?: string;
    type: EntityType;
    label: string;
    capabilities: {
        edit: boolean;
        createAsset: boolean;
        createCollection: boolean;
    };
    workspaceId: string;
};
