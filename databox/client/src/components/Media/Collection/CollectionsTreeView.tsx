import React, {useCallback, useEffect, useState} from 'react';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import ChevronRightIcon from '@mui/icons-material/ChevronRight';
import TreeItem from '@mui/lab/TreeItem';
import {CollectionOptionalWorkspace, Workspace} from '../../../types';
import {getCollection, getWorkspaces} from '../../../api/collection';
import {TreeView} from '@mui/lab';
import {
    Box,
    CircularProgress,
    IconButton,
    Stack,
    Typography,
} from '@mui/material';
import EditableCollectionTree, {
    defaultNewCollectionName,
    nodeNewPrefix,
} from './EditableTree';
import CreateNewFolderIcon from '@mui/icons-material/CreateNewFolder';

const nodeSeparator = '|';
export const newCollectionPathSeparator = '\\|\\';

export {nodeSeparator as treeViewPathSeparator};

export type SetExpanded = (
    nodeIds: string[] | ((prevNodeIds: string[]) => string[])
) => void;

type CollectionTreeProps = {
    newCollectionPath: NewCollectionPathState | undefined;
    collection: CollectionOptionalWorkspace;
    workspaceId: string;
    depth?: number;
    disabledBranches?: string[];
    setNewCollectionPath: SetNewCollectionPath;
    updateCollectionPath: UpdateCollectionPath;
    setExpanded: SetExpanded;
    allowNew: boolean | undefined;
};

export type UpdateCollectionPath = (
    index: number,
    id: string | null,
    value?: string | null,
    editing?: boolean
) => void;

export type NewCollectionPath = {
    rootId: string;
    path: string[];
};

export type CollectionId = string;

export type Collection = CollectionId | NewCollectionPath;

function CollectionTree({
    updateCollectionPath,
    newCollectionPath,
    setNewCollectionPath,
    collection,
    workspaceId,
    disabledBranches,
    setExpanded,
    allowNew,
    depth = 0,
}: CollectionTreeProps) {
    const [loaded, setLoaded] = React.useState(false);
    const [tree, setTree] = React.useState<
        CollectionOptionalWorkspace[] | undefined
    >(collection.children);

    async function load() {
        if (!collection.children || collection.children.length === 0) {
            return;
        }

        if (!loaded) {
            setLoaded(true);
            const r = await getCollection(collection.id);
            setTree(r.children);
        }
    }

    const collectionIRI = collection['@id'];
    const nodeId = workspaceId + nodeSeparator + collectionIRI;
    const hasTree = tree && tree.length > 0;
    const hasNewCollectionPath =
        newCollectionPath && newCollectionPath.rootNode === nodeId;

    const onCreateNewCollection = useCallback(
        (e: React.MouseEvent<HTMLButtonElement>) => {
            e.stopPropagation();
            setNewCollectionPath(
                [
                    {
                        value: defaultNewCollectionName,
                        id: '0',
                        editing: true,
                    },
                ],
                nodeId
            );
            setExpanded(prev =>
                !prev.includes(nodeId) ? prev.concat(nodeId) : prev
            );
        },
        [setNewCollectionPath, setExpanded, nodeId]
    );

    return (
        <TreeItem
            disabled={
                disabledBranches &&
                disabledBranches.some(b => nodeId.startsWith(b))
            }
            onClick={load}
            nodeId={nodeId}
            label={
                <Stack direction={'row'} alignItems={'center'}>
                    {collection.title}
                    {allowNew && collection.capabilities.canEdit && (
                        <IconButton
                            sx={{ml: 1}}
                            onClick={onCreateNewCollection}
                        >
                            <CreateNewFolderIcon />
                        </IconButton>
                    )}
                </Stack>
            }
        >
            {/*Wrapping all to avoid collapse in node */}
            {hasTree || (allowNew && hasNewCollectionPath) ? (
                <>
                    {allowNew && hasNewCollectionPath ? (
                        <EditableCollectionTree
                            nodes={newCollectionPath!.nodes}
                            offset={0}
                            onEdit={updateCollectionPath}
                            setExpanded={setExpanded}
                        />
                    ) : null}
                    {hasTree &&
                        tree!.map(c => (
                            <CollectionTree
                                key={c.id}
                                workspaceId={workspaceId}
                                collection={c}
                                depth={depth + 1}
                                newCollectionPath={
                                    newCollectionPath &&
                                    newCollectionPath.rootNode === collectionIRI
                                        ? undefined
                                        : newCollectionPath
                                }
                                setNewCollectionPath={setNewCollectionPath}
                                updateCollectionPath={updateCollectionPath}
                                disabledBranches={disabledBranches}
                                setExpanded={setExpanded}
                                allowNew={allowNew}
                            />
                        ))}
                </>
            ) : null}
        </TreeItem>
    );
}

function normalizeNodeId(
    nodeId: string,
    newCollectionPath: NewCollectionPathState | undefined
): Collection {
    if (newCollectionPath && nodeId.startsWith(nodeNewPrefix)) {
        const offset = parseInt(nodeId.substring(nodeNewPrefix.length));

        return {
            rootId: newCollectionPath.rootNode,
            path: new Array(offset + 1)
                .fill(true, 0, offset + 1)
                .map((_, i) => newCollectionPath.nodes[i].value),
        };
    }

    return nodeId.split(nodeSeparator)[1];
}

export type NewCollectionNodeState = {
    id: string;
    value: string;
    editing?: boolean | undefined;
};

type NewCollectionPathState = {
    rootNode: string;
    nodes: NewCollectionNodeState[];
};

type SetNewCollectionPath = (
    nodes: NewCollectionNodeState[],
    rootId?: string
) => void;

type Props<IsMulti extends boolean = false> = {
    onChange?: (
        selection: IsMulti extends true ? string[] : string,
        workspaceId?: IsMulti extends true ? string : never
    ) => void;
    value?: IsMulti extends true ? Collection[] : Collection;
    multiple?: IsMulti;
    workspaceId?: string;
    disabledBranches?: string[];
    allowNew?: boolean;
    disabled?: boolean | undefined;
};

export type {Props as CollectionTreeViewProps};

export function CollectionsTreeView<IsMulti extends boolean = false>({
    onChange,
    value,
    multiple,
    workspaceId,
    disabledBranches,
    allowNew,
    disabled,
}: Props<IsMulti>) {
    const [workspaces, setWorkspaces] = useState<Workspace[]>();
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
                    ? striped.rootId?.split(nodeSeparator)[0]
                    : (nodeIds as string).split(nodeSeparator)[0];

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

    useEffect(() => {
        getWorkspaces().then(w => {
            if (workspaceId) {
                setWorkspaces(w.filter(i => i.id === workspaceId));
            } else {
                setWorkspaces(w);
            }
        });
    }, [workspaceId]);

    const handleToggle = (_event: React.ChangeEvent<{}>, nodeIds: string[]) => {
        setExpanded(nodeIds);
    };

    if (!workspaces) {
        return <CircularProgress size={50} />;
    }

    return (
        <TreeView
            sx={{
                'flexGrow': 1,
                'maxWidth': 400,
                '.MuiTreeItem-content.Mui-selected, .MuiTreeItem-content.Mui-selected.Mui-focused':
                    {
                        bgcolor: 'success.main',
                        color: 'success.contrastText',
                        fontWeight: 700,
                    },
                '.MuiButtonBase-root': {
                    color: 'inherit',
                },
            }}
            defaultCollapseIcon={<ExpandMoreIcon />}
            defaultExpandIcon={<ChevronRightIcon />}
            expanded={expanded}
            selected={selected as any}
            onNodeToggle={handleToggle}
            onNodeSelect={handleSelect as any}
            multiSelect={multiple || false}
        >
            {workspaces.map(w => {
                const nodeId = w.id + nodeSeparator + w['@id'];
                return (
                    <TreeItem
                        nodeId={nodeId}
                        key={w.id}
                        label={
                            <>
                                <Box
                                    sx={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        p: 0.5,
                                        pr: 0,
                                    }}
                                >
                                    <Typography
                                        variant="body2"
                                        sx={{
                                            fontWeight: 'inherit',
                                            flexGrow: 1,
                                        }}
                                    >
                                        {w.name}
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
                        {w.collections.map(c => (
                            <CollectionTree
                                key={c.id}
                                workspaceId={w.id}
                                collection={c}
                                disabledBranches={disabledBranches}
                                newCollectionPath={newCollectionPath}
                                setNewCollectionPath={setNewCollectionPathProxy}
                                updateCollectionPath={updateCollectionPath}
                                setExpanded={setExpanded}
                                allowNew={allowNew}
                            />
                        ))}
                    </TreeItem>
                );
            })}
        </TreeView>
    );
}
