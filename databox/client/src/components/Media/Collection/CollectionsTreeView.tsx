import React, {useCallback, useEffect, useState} from 'react';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import ChevronRightIcon from '@mui/icons-material/ChevronRight';
import TreeItem from '@mui/lab/TreeItem';
import {CollectionOptionalWorkspace, Workspace} from "../../../types";
import {getCollection, getWorkspaces} from "../../../api/collection";
import {TreeView, useTreeItem} from "@mui/lab";
import {Box, CircularProgress, IconButton, Stack, Typography} from "@mui/material";
import EditableCollectionTree from "./EditableTree";
import CreateNewFolderIcon from '@mui/icons-material/CreateNewFolder';

const nodeSeparator = '|';

export {nodeSeparator as treeViewPathSeparator};

type CollectionTreeProps = {
    newCollectionPath: NewCollectionPath | undefined;
    collection: CollectionOptionalWorkspace;
    workspaceId: string;
    depth?: number,
    disabledBranches?: string[];
    setNewCollectionPath: SetNewCollectionPath;
    updateCollectionPath: UpdateCollectionPath;
}

type UpdateCollectionPath = (index: number, value: string | null) => void;

function CollectionTree({
                            updateCollectionPath,
                            newCollectionPath,
                            setNewCollectionPath,
                            collection,
                            workspaceId,
                            disabledBranches,
                            depth = 0
                        }: CollectionTreeProps) {
    const [loaded, setLoaded] = React.useState(false);
    const [tree, setTree] = React.useState<CollectionOptionalWorkspace[] | undefined>(collection.children);

    async function load() {
        if (!collection.children || collection.children.length === 0) {
            return;
        }

        if (!loaded) {
            setLoaded(true);
            const r = await getCollection(collection.id)
            setTree(r.children);
        }
    }

    const nodeId = workspaceId + nodeSeparator + collection['@id'];
    const hasTree = tree && tree.length > 0;
    const hasNewCollectionPath = newCollectionPath && newCollectionPath.rootNode === collection.id;

    const {
        expanded,
    } = useTreeItem(nodeId);

    const onCreateNewCollection = useCallback((e: React.MouseEvent<HTMLButtonElement>) => {
        if (expanded) {
            e.stopPropagation();
        } else {
            const target: HTMLButtonElement = e.currentTarget;
            setTimeout(() => {
                target.click();
            }, 50);
        }
        setNewCollectionPath(['Collection'], collection.id);
    }, [setNewCollectionPath, expanded]);

    return <TreeItem
        disabled={disabledBranches && disabledBranches.some(b => nodeId.startsWith(b))}
        onClick={load}
        nodeId={nodeId}
        label={<Stack
            direction={'row'}
            alignItems={'center'}
        >
            {collection.title}
            {collection.capabilities.canEdit && <IconButton
                sx={{ml: 1}}
                onClick={onCreateNewCollection}
            >
                <CreateNewFolderIcon/>
            </IconButton>}
        </Stack>}
    >
        {/*Wrapping all to avoid collapse in node */}
        {(hasTree || hasNewCollectionPath) ? <>
            {newCollectionPath && newCollectionPath.rootNode === collection.id ? <EditableCollectionTree
                path={newCollectionPath.path}
                offset={0}
                onEdit={updateCollectionPath}
            /> : null}
            {hasTree && tree!.map(c => <CollectionTree
                key={c.id}
                workspaceId={workspaceId}
                collection={c}
                depth={depth + 1}
                newCollectionPath={newCollectionPath && newCollectionPath.rootNode === collection.id ? undefined : newCollectionPath}
                setNewCollectionPath={setNewCollectionPath}
                updateCollectionPath={updateCollectionPath}
                disabledBranches={disabledBranches}
            />)}
        </> : null}
    </TreeItem>
}

function stripWs(nodeId: string): string {
    return nodeId.split(nodeSeparator)[1];
}

type NewCollectionPath = {
    rootNode: string | null;
    path: string[];
};

type SetNewCollectionPath = (path: string[], rootId?: string) => void;

type Props<IsMulti extends boolean = false> = {
    onChange?: (selection: IsMulti extends true ? string[] : string, workspaceId?: IsMulti extends true ? string : never) => void;
    value?: IsMulti extends true ? string[] : string;
    multiple?: IsMulti;
    workspaceId?: string;
    disabledBranches?: string[];
}

export function CollectionsTreeView<IsMulti extends boolean = false>({
                                                                         onChange,
                                                                         value,
                                                                         multiple,
                                                                         workspaceId,
                                                                         disabledBranches,
                                                                     }: Props<IsMulti>) {
    const [workspaces, setWorkspaces] = useState<Workspace[]>();
    const [newCollectionPath, setNewCollectionPath] = useState<NewCollectionPath>();
    const [expanded, setExpanded] = React.useState<string[]>([]);
    const [selected, setSelected] = React.useState<IsMulti extends true ? string[] : (string | undefined)>(value ?? (multiple ? [] : '') as any);

    const setNewCollectionPathProxy = useCallback<SetNewCollectionPath>((path, rootId) => {
        setNewCollectionPath(prev => ({
            path,
            rootNode: rootId ? rootId : prev!.rootNode,
        }));
    }, [setNewCollectionPath]);

    const updateCollectionPath = useCallback<UpdateCollectionPath>((index: number, value: string | null) => {
        setNewCollectionPath((prev) => {
            if (index === 0 && value === null) {
                return undefined;
            }

            if (index >= prev!.path?.length ?? 0) {
                return {
                    ...prev!,
                    path: prev!.path.concat(value!),
                };
            }

            return {
                ...prev!,
                path: value === null ? prev!.path.slice(0, index) : prev!.path.map((p, i) => i === index ? (value as string) : p),
            };
        });
    }, [setNewCollectionPath]);

    useEffect(() => {
        getWorkspaces().then(w => {
            if (workspaceId) {
                setWorkspaces(w.filter(i => i.id === workspaceId));
            } else {
                setWorkspaces(w);
            }
        });
    }, [workspaceId]);

    const handleToggle = (event: React.ChangeEvent<{}>, nodeIds: string[]) => {
        setExpanded(nodeIds);
    };

    const handleSelect = (event: React.ChangeEvent<{}>, nodeIds: IsMulti extends true ? string[] : string) => {
        if (multiple) {
            const striped = (nodeIds as string[]).map(stripWs);
            setSelected(nodeIds as any);
            onChange && onChange(striped as any);
        } else {
            const striped = stripWs(nodeIds as string);
            setSelected(nodeIds);
            onChange && onChange(striped as any, (nodeIds as string).split(nodeSeparator)[0] as any);
        }
    };

    if (!workspaces) {
        return <CircularProgress
            size={50}
        />
    }

    return <TreeView
        sx={{
            flexGrow: 1,
            maxWidth: 400,
            '.Mui-selected, .Mui-selected.Mui-focused': {
                backgroundColor: 'success.main',
                color: 'success.contrastText',
            }
        }}
        defaultCollapseIcon={<ExpandMoreIcon/>}
        defaultExpandIcon={<ChevronRightIcon/>}
        expanded={expanded}
        selected={selected as any}
        onNodeToggle={handleToggle}
        onNodeSelect={handleSelect as any}
        multiSelect={multiple || false}
    >
        {workspaces.map(w => {
            const nodeId = w.id + nodeSeparator + w['@id'];
            return <TreeItem
                nodeId={nodeId}
                key={w.id}
                label={<>
                    <Box sx={{ display: 'flex', alignItems: 'center', p: 0.5, pr: 0 }}>
                        <Typography variant="body2" sx={{ fontWeight: 'inherit', flexGrow: 1 }}>
                            {w.name}
                        </Typography>
                        <Typography variant="caption" color="inherit">
                        </Typography>
                    </Box>
                </>}
                disabled={disabledBranches && disabledBranches.some(b => nodeId.startsWith(b))}
            >
                {w.collections.map(c => <CollectionTree
                    key={c.id}
                    workspaceId={w.id}
                    collection={c}
                    disabledBranches={disabledBranches}
                    newCollectionPath={newCollectionPath}
                    setNewCollectionPath={setNewCollectionPathProxy}
                    updateCollectionPath={updateCollectionPath}
                />)}
            </TreeItem>;
        })}
    </TreeView>
}
