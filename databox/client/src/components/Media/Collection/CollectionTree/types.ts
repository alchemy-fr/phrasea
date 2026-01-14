export type WorkspaceOrCollectionTreeItem = {
    '@id'?: string;
    'id'?: string;
    'label': string;
    'capabilities': {
        canEdit: boolean;
    };
    'workspaceId': string;
};
export type CollectionId = string;
