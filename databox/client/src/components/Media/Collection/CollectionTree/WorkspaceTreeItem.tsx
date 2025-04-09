import {Box, Typography} from '@mui/material';
import {CollectionTreeItem} from './CollectionTreeItem.tsx';
import {TreeItem} from '@mui/x-tree-view';
import React from 'react';
import {Workspace} from '../../../../types.ts';
import {CommonTreeItemProps, treeViewPathSeparator} from './collectionTree.ts';
import {
    CollectionPager,
    useCollectionStore,
} from '../../../../store/collectionStore.ts';
import TreeItemLoader from './TreeItemLoader.tsx';

type Props<IsMulti extends boolean = false> = {
    workspace: Workspace;
} & CommonTreeItemProps<IsMulti>;

export default function WorkspaceTreeItem<IsMulti extends boolean = false>({
    workspace,
    disabledBranches,
    ...rest
}: Props<IsMulti>) {
    const workspaceId = workspace.id;
    const nodeId = workspaceId + treeViewPathSeparator + workspace['@id'];
    const [loaded, setLoaded] = React.useState(false);
    const loadRoot = useCollectionStore(state => state.load);

    const pager =
        useCollectionStore(state => state.tree)[workspaceId] ??
        ({
            items: [],
            expanding: false,
            loadingMore: false,
        } as CollectionPager);

    async function load() {
        if (!loaded) {
            setLoaded(true);
            await loadRoot(workspaceId);
        }
    }

    return (
        <>
            <TreeItem
                nodeId={nodeId}
                key={workspaceId}
                onClick={load}
                label={
                    <>
                        <Box
                            sx={{
                                display: 'flex',
                                alignItems: 'center',
                                p: 1,
                            }}
                        >
                            <Typography
                                variant="body1"
                                sx={{
                                    fontWeight: 'inherit',
                                    flexGrow: 1,
                                }}
                            >
                                {workspace.nameTranslated}
                            </Typography>
                            <Typography
                                variant="caption"
                                color="inherit"
                            ></Typography>
                        </Box>
                    </>
                }
                disabled={
                    disabledBranches &&
                    disabledBranches.some(b => nodeId.startsWith(b))
                }
            >
                {pager.expanding ? <TreeItemLoader /> : null}
                {pager.items.map(c => (
                    <CollectionTreeItem
                        {...rest}
                        key={c.id}
                        workspaceId={workspaceId}
                        collection={c}
                        disabledBranches={disabledBranches}
                    />
                ))}
            </TreeItem>
        </>
    );
}
