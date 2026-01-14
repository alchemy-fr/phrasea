export enum EntityType {
    Workspace = 1,
    Collection = 2,
}

export type WorkspaceOrCollectionTreeItem = {
    id?: string;
    type: EntityType;
    label: string;
    capabilities: {
        canEdit: boolean;
    };
    workspaceId: string;
};
export type CollectionId = string;
