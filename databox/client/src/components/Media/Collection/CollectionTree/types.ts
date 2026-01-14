export type WorkspaceOrCollectionTreeItem = {
    '@id'?: string;
    'id': string;
    'label': string;
    'capabilities': {
        canEdit: boolean;
    };
};
export type CollectionId = string;
