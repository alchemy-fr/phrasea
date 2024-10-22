import React, {useCallback, useState} from 'react';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import ChevronRightIcon from '@mui/icons-material/ChevronRight';
import {TreeView} from '@mui/x-tree-view';
import {CircularProgress} from '@mui/material';
import useEffectOnce from '@alchemy/react-hooks/src/useEffectOnce';
import {useWorkspaceStore} from '../../../../store/workspaceStore.ts';
import {
    Collection,
    CommonTreeProps,
    NewCollectionPathState,
    normalizeNodeId,
    SetNewCollectionPath,
    treeViewPathSeparator,
    UpdateCollectionPath,
} from './collectionTree.ts';
import WorkspaceTreeItem from './WorkspaceTreeItem.tsx';

type Props<IsMulti extends boolean = false> = {
    value?: IsMulti extends true ? Collection[] : Collection;
    onChange?: (
        selection: IsMulti extends true ? string[] : string,
        workspaceId?: IsMulti extends true ? string : never
    ) => void;
    workspaceId?: string;
} & CommonTreeProps<IsMulti>;

export type {Props as CollectionTreeViewProps};

export function CollectionsTreeView<IsMulti extends boolean = false>({
    onChange,
    value,
    multiple,
    workspaceId,
    disabledBranches,
    allowNew,
    disabled,
    isSelectable,
}: Props<IsMulti>) {
    const loadWorkspaces = useWorkspaceStore(state => state.load);
    const loading = useWorkspaceStore(state => state.loading);
    const allWorkspaces = useWorkspaceStore(state => state.workspaces);
    const workspaces = workspaceId
        ? allWorkspaces.filter(w => w.id === workspaceId)
        : allWorkspaces;

    useEffectOnce(() => {
        loadWorkspaces();
    }, []);

    const [newCollectionPath, setNewCollectionPath] =
        useState<NewCollectionPathState>();
    const [expanded, setExpanded] = React.useState<string[]>([]);
    const [selected, setSelected] = React.useState<
        IsMulti extends true ? string[] : string | undefined
    >(value ?? ((multiple ? [] : '') as any));

    const setNewCollectionPathProxy = useCallback<SetNewCollectionPath>(
        (nodes, rootId) => {
            setNewCollectionPath(prev => ({
                nodes,
                rootNode: rootId ? rootId : prev!.rootNode,
            }));
        },
        [setNewCollectionPath]
    );

    const handleSelect = (
        _event: React.ChangeEvent<{}>,
        nodeIds: IsMulti extends true ? string[] : string
    ) => {
        if (disabled) {
            return;
        }
        if (multiple) {
            const striped = (nodeIds as string[]).map(i =>
                normalizeNodeId(i, newCollectionPath)
            );
            setSelected(nodeIds as any);
            onChange && onChange(striped as any);
        } else {
            const striped = normalizeNodeId(
                nodeIds as string,
                newCollectionPath
            );
            const workspaceId =
                typeof striped === 'object'
                    ? striped.rootId?.split(treeViewPathSeparator)[0]
                    : (nodeIds as string).split(treeViewPathSeparator)[0];

            setSelected(nodeIds);
            onChange && onChange(striped as any, workspaceId as any);
        }
    };

    const updateCollectionPath = useCallback<UpdateCollectionPath>(
        (index, id, value, editing) => {
            setNewCollectionPath(prev => {
                if (index === 0 && id === null) {
                    return undefined;
                }

                if (index >= (prev!.nodes?.length ?? 0)) {
                    return {
                        ...prev!,
                        nodes: prev!.nodes.concat({
                            id: id!,
                            value: value!,
                            editing: editing!,
                        }),
                    };
                }

                return {
                    ...prev!,
                    nodes:
                        id === null
                            ? prev!.nodes.slice(0, index)
                            : prev!.nodes.map((p, i) =>
                                  i === index
                                      ? {
                                            id: id!,
                                            value: value!,
                                            editing: editing!,
                                        }
                                      : p
                              ),
                };
            });
        },
        [setNewCollectionPath]
    );

    const handleToggle = (_event: React.ChangeEvent<{}>, nodeIds: string[]) => {
        setExpanded(nodeIds);
    };

    if (loading) {
        return <CircularProgress size={50} />;
    }

    return (
        <TreeView
            sx={theme => ({
                'flexGrow': 1,
                '.MuiTreeItem-content': {
                    borderRadius: theme.shape.borderRadius,
                    width: 'fit-content',
                },
                '.MuiTreeItem-content.Mui-selected, .MuiTreeItem-content.Mui-selected.Mui-focused':
                    {
                        bgcolor: 'primary.main',
                        color: 'primary.contrastText',
                        fontWeight: 700,
                    },
                '.MuiButtonBase-root': {
                    color: 'inherit',
                },
            })}
            defaultCollapseIcon={<ExpandMoreIcon />}
            defaultExpandIcon={<ChevronRightIcon />}
            expanded={expanded}
            selected={selected as any}
            onNodeToggle={handleToggle}
            onNodeSelect={handleSelect as any}
            multiSelect={multiple || false}
        >
            {workspaces.map(w => {
                return (
                    <WorkspaceTreeItem
                        key={w.id}
                        workspace={w}
                        isSelectable={isSelectable}
                        disabledBranches={disabledBranches}
                        newCollectionPath={newCollectionPath}
                        setNewCollectionPath={setNewCollectionPathProxy}
                        updateCollectionPath={updateCollectionPath}
                        setExpanded={setExpanded}
                        allowNew={allowNew}
                    />
                );
            })}
        </TreeView>
    );
}
