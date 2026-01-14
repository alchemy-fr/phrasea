import {useWorkspaceStore} from '../../../../store/workspaceStore.ts';
import React, {useCallback} from 'react';
import {CircularProgress} from '@mui/material';
import {CollectionOptionalWorkspace} from '../../../../types.ts';
import {useCollectionStore} from '../../../../store/collectionStore.ts';
import useEffectOnce from '@alchemy/react-hooks/src/useEffectOnce.ts';
import {
    LoadNodeChildren,
    TreeNode,
    TreeView,
    TreeViewOptionsProps,
    useVirtualNodes,
} from '@alchemy/phrasea-framework';
import {WorkspaceOrCollectionTreeItem} from './types.ts';
import CollectionTreeNode from './CollectionTreeNode.tsx';
import {useTranslation} from 'react-i18next';
import CollectionEdit from './CollectionEdit.tsx';

type Props<IsMulti extends boolean = false> = {
    value?: IsMulti extends true ? string[] : string;
    onChange?: (
        selection: IsMulti extends true ? string[] : string,
        workspaceId?: IsMulti extends true ? string : never
    ) => void;
    workspaceId?: string;
    allowNew?: boolean;
} & TreeViewOptionsProps<WorkspaceOrCollectionTreeItem>;
export type {Props as CollectionTreeViewProps};

export default function CollectionsTreeView<IsMulti extends boolean = false>({
    workspaceId,
    allowNew,
    ...treeViewProps
}: Props<IsMulti>) {
    const {t} = useTranslation();
    const loadWorkspaces = useWorkspaceStore(state => state.load);
    const loadWorkspaceCollections = useCollectionStore(state => state.load);
    const loading = useWorkspaceStore(state => state.loading);
    const allWorkspaces = useWorkspaceStore(state => state.workspaces);

    const workspaces = workspaceId
        ? allWorkspaces.filter(w => w.id === workspaceId)
        : allWorkspaces;

    const collectionsTree = useCollectionStore(state => state.tree);

    useEffectOnce(() => {
        loadWorkspaces();
    }, []);

    const loadChildren = useCallback<
        LoadNodeChildren<WorkspaceOrCollectionTreeItem>
    >(
        async node => {
            const [workspaceId, ...collectionPath] = node.id.split('/');

            await loadWorkspaceCollections(
                workspaceId,
                collectionPath[collectionPath.length - 1]
            );
        },
        [loadWorkspaceCollections]
    );

    const items = React.useMemo<
        TreeNode<WorkspaceOrCollectionTreeItem>[]
    >(() => {
        const mapCollection = (
            pathPrefix: string,
            collection: CollectionOptionalWorkspace
        ): TreeNode<WorkspaceOrCollectionTreeItem> => {
            const nodeId = `${pathPrefix}${collection.id}`;

            const children =
                collectionsTree[collection.id]?.items ?? collection.children;

            return {
                id: nodeId,
                data: {
                    id: collection.id,
                    label: collection.titleTranslated || collection.title,
                    capabilities: collection.capabilities,
                },
                hasChildren: children ? children.length > 0 : false,
                childrenLoaded: !!collectionsTree[collection.id],
                children: children?.map(c => mapCollection(`${nodeId}/`, c)),
                canAddChildren: collection.capabilities.canEdit,
            };
        };

        return workspaces.map(w => {
            const nodeId = w.id;
            return {
                id: nodeId,
                hasChildren: true,
                data: {
                    id: w.id,
                    label: w.nameTranslated || w.name,
                    capabilities: w.capabilities,
                },
                children: collectionsTree[w.id]?.items.map(c =>
                    mapCollection(`${nodeId}/`, c)
                ),
            };
        });
    }, [workspaces, collectionsTree]);

    const {normalizedNodes, ...editingProps} = useVirtualNodes({
        nodes: items,
        newItem: {
            label: t('collection.tree_view.new_collection', 'New Collection'),
            capabilities: {
                canEdit: true,
            },
        },
    });

    if (loading) {
        return <CircularProgress size={50} />;
    }

    return (
        <TreeView
            {...treeViewProps}
            loadChildren={loadChildren}
            renderNodeLabel={props => {
                return <CollectionTreeNode {...props} />;
            }}
            editNodeComponent={CollectionEdit}
            nodes={normalizedNodes}
            {...(allowNew ? editingProps : {})}
        />
    );
}
