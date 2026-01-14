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
import {EntityType, WorkspaceOrCollectionTreeItem} from './types.ts';
import CollectionTreeNode from './CollectionTreeNode.tsx';
import {useTranslation} from 'react-i18next';
import CollectionEdit from './CollectionEdit.tsx';

type Data = WorkspaceOrCollectionTreeItem;

type Props<IsMulti extends boolean = false> = {
    value?: IsMulti extends true ? string[] : string;
    onChange?: (
        selection: IsMulti extends true ? TreeNode<Data>[] : TreeNode<Data>
    ) => void;
    workspaceId?: string;
    allowNew?: boolean;
    multiple?: IsMulti;
} & Omit<TreeViewOptionsProps<Data>, 'multiple'>;

export type {Props as CollectionTreeViewProps};

export default function CollectionsTreeView<IsMulti extends boolean = false>({
    workspaceId,
    allowNew,
    multiple,
    onChange,
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

    const loadChildren = useCallback<LoadNodeChildren<Data>>(
        async node => {
            await loadWorkspaceCollections(node.data.workspaceId, node.id);
        },
        [loadWorkspaceCollections]
    );

    const items = React.useMemo<TreeNode<Data>[]>(() => {
        const mapCollection = (
            collection: CollectionOptionalWorkspace,
            workspaceId: string
        ): TreeNode<Data> => {
            const nodeId = collection.id;

            const children =
                collectionsTree[collection.id]?.items ?? collection.children;

            return {
                id: nodeId,
                data: {
                    id: collection.id,
                    type: EntityType.Collection,
                    label: collection.titleTranslated || collection.title,
                    capabilities: collection.capabilities,
                    workspaceId,
                },
                hasChildren: children ? children.length > 0 : false,
                childrenLoaded: !!collectionsTree[collection.id],
                children: children?.map(c => mapCollection(c, workspaceId)),
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
                    type: EntityType.Workspace,
                    label: w.nameTranslated || w.name,
                    capabilities: w.capabilities,
                    workspaceId: w.id,
                },
                children: collectionsTree[w.id]?.items.map(c =>
                    mapCollection(c, w.id)
                ),
            };
        });
    }, [workspaces, collectionsTree]);

    const {normalizedNodes, ...editingProps} = useVirtualNodes({
        nodes: items,
        newItem: parentNode => ({
            label: t('collection.tree_view.new_collection', 'New Collection'),
            type: EntityType.Collection,
            capabilities: {
                canEdit: true,
            },
            workspaceId: parentNode!.data.workspaceId,
        }),
    });

    if (loading) {
        return <CircularProgress size={50} />;
    }

    return (
        <TreeView
            {...treeViewProps}
            loadChildren={loadChildren}
            onSelectionChange={selection => {
                // @ts-expect-error TS can't infer multiple is false here
                onChange?.(multiple ? selection : selection[0]);
            }}
            renderNodeLabel={props => {
                return <CollectionTreeNode {...props} />;
            }}
            multiple={multiple}
            editNodeComponent={CollectionEdit}
            nodes={normalizedNodes}
            {...(allowNew ? editingProps : {})}
        />
    );
}
