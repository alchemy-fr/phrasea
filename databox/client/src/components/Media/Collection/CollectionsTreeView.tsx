import React, {useEffect, useState} from 'react';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import ChevronRightIcon from '@mui/icons-material/ChevronRight';
import TreeItem from '@mui/lab/TreeItem';
import {Collection, Workspace} from "../../../types";
import {getCollection, getWorkspaces} from "../../../api/collection";
import {TreeView} from "@mui/lab";
import {CircularProgress} from "@mui/material";

type Props<IsMulti extends boolean = false> = {
    onChange?: (selection: IsMulti extends true ? string[] : string, workspaceId?: IsMulti extends true ? string : never) => void;
    value?: IsMulti extends true ? string[] : string;
    multiple?: IsMulti;
}

type CollectionTreeProps = {
    collection: Collection;
    workspaceId: string;
    depth?: number,
}

const nodeSeparator = '|';

function CollectionTree({
                            collection,
                            workspaceId,
                            depth = 0
}: CollectionTreeProps) {
    const [loaded, setLoaded] = React.useState(false);
    const [tree, setTree] = React.useState<Collection[] | undefined>(collection.children);

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

    return <TreeItem
        onClick={load}
        nodeId={workspaceId+nodeSeparator+collection['@id']}
        label={collection.title}
    >
        {tree && tree.map(c => <CollectionTree
            key={c.id}
            workspaceId={workspaceId}
            collection={c} depth={depth + 1} />)}
    </TreeItem>
}


function stripWs(nodeId: string): string {
    return nodeId.split(nodeSeparator)[1];
}

export function CollectionsTreeView<IsMulti extends boolean = false>({
                                        onChange,
                                        value,
                                        multiple,
}: Props<IsMulti>) {
    const [workspaces, setWorkspaces] = useState<Workspace[]>();

    useEffect(() => {
        getWorkspaces().then(setWorkspaces);
    }, []);

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
        {workspaces.map(w => <TreeItem
            nodeId={w.id+nodeSeparator+w['@id']}
            key={w.id}
            label={w.name}
        >
            {w.collections.map(c => <CollectionTree
                key={c.id}
                workspaceId={w.id}
                collection={c}
            />)}
        </TreeItem>)}
    </TreeView>
}
