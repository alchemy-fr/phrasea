import {
    RichTreeView,
    TreeViewDefaultItemModelProperties,
} from '@mui/x-tree-view';
import {CollectionIdOrPath, CommonTreeProps} from './collectionTree.ts';
import {useWorkspaceStore} from '../../../../store/workspaceStore.ts';
import React from 'react';
import {CircularProgress} from '@mui/material';
import {CollectionOptionalWorkspace} from '../../../../types.ts';
import {useCollectionStore} from '../../../../store/collectionStore.ts';
import useEffectOnce from '@alchemy/react-hooks/src/useEffectOnce.ts';

type Props<IsMulti extends boolean = false> = {
    value?: IsMulti extends true ? CollectionIdOrPath[] : CollectionIdOrPath;
    onChange?: (
        selection: IsMulti extends true ? string[] : string,
        workspaceId?: IsMulti extends true ? string : never
    ) => void;
    workspaceId?: string;
} & CommonTreeProps<IsMulti>;
export type {Props as CollectionTreeViewProps2};

export default function CollectionsTreeView2<IsMulti extends boolean = false>({
    workspaceId,
}: Props<IsMulti>) {
    const [selectedWorkspace, setSelectedWorkspace] = React.useState<
        string | undefined
    >();
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

    useEffectOnce(() => {
        if (selectedWorkspace) {
            loadWorkspaceCollections(selectedWorkspace);
        }
    }, [loadWorkspaceCollections, selectedWorkspace]);

    if (loading) {
        return <CircularProgress size={50} />;
    }

    const mapCollection = (
        pathPrefix: string,
        collection: CollectionOptionalWorkspace
    ): TreeViewDefaultItemModelProperties => {
        return {
            id: pathPrefix + collection.id,
            label: collection.titleTranslated,
            children: collection.children?.map(c =>
                mapCollection(`${pathPrefix}${collection.id}/`, c)
            ),
        };
    };

    console.log('collectionsTree', collectionsTree);

    const items: TreeViewDefaultItemModelProperties[] = workspaces.map(w => ({
        id: w.id,
        label: w.nameTranslated || w.name,
        children:
            collectionsTree[w.id]?.items.map(c =>
                mapCollection(`${w.id}/`, c)
            ) || [],
    }));

    console.log('items', items);

    return (
        <>
            <RichTreeView
                items={items}
                isItemEditable
                defaultExpandedItems={['grid', 'pickers']}
                onItemSelectionToggle={(_e, itemId, selected) => {
                    setSelectedWorkspace(selected ? itemId : undefined);
                }}
            />
        </>
    );
}
