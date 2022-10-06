import React, {useEffect, useState} from 'react';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import ChevronRightIcon from '@mui/icons-material/ChevronRight';
import TreeItem from '@mui/lab/TreeItem';
import {CollectionOptionalWorkspace, Workspace} from "../../../types";
import {getCollection, getWorkspaces} from "../../../api/collection";
import {TreeView} from "@mui/lab";
import {Box, CircularProgress, Typography} from "@mui/material";

type Props<IsMulti extends boolean = false> = {
    onChange?: (selection: IsMulti extends true ? string[] : string, workspaceId?: IsMulti extends true ? string : never) => void;
    value?: IsMulti extends true ? string[] : string;
    multiple?: IsMulti;
    workspaceId?: string;
    disabledBranches?: string[];
}

type CollectionTreeProps = {
    collection: CollectionOptionalWorkspace;
    workspaceId: string;
    depth?: number,
    disabledBranches?: string[];
}

const nodeSeparator = '|';

export {nodeSeparator as treeViewPathSeparator};

function CollectionTree({
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
    return <TreeItem
        disabled={disabledBranches && disabledBranches.some(b => nodeId.startsWith(b))}
        onClick={load}
        nodeId={nodeId}
        label={collection.title}
    >
        {tree && tree.map(c => <CollectionTree
            key={c.id}
            workspaceId={workspaceId}
            collection={c}
            depth={depth + 1}
            disabledBranches={disabledBranches}
        />)}
    </TreeItem>
}

function stripWs(nodeId: string): string {
    return nodeId.split(nodeSeparator)[1];
}

type NewCollectionPath = {
    rootNode: string;
    path: string;
};

export function CollectionsTreeView<IsMulti extends boolean = false>({
                                                                         onChange,
                                                                         value,
                                                                         multiple,
                                                                         workspaceId,
                                                                         disabledBranches,
                                                                     }: Props<IsMulti>) {
    const [workspaces, setWorkspaces] = useState<Workspace[]>();
    const [newCollectionPath, setNewCollectionPath] = useState<NewCollectionPath>();

    useEffect(() => {
        getWorkspaces().then(w => {
            if (workspaceId) {
                setWorkspaces(w.filter(i => i.id === workspaceId));
            } else {
                setWorkspaces(w);
            }
        });
    }, [workspaceId]);

    const [expanded, setExpanded] = React.useState<string[]>([]);
    const [selected, setSelected] = React.useState<IsMulti extends true ? string[] : string>(value ?? (multiple ? [] : '') as any);

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
                />)}
            </TreeItem>;
        })}
    </TreeView>
}
