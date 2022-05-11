import React from 'react';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import ChevronRightIcon from '@mui/icons-material/ChevronRight';
import TreeItem from '@mui/lab/TreeItem';
import {Collection, Workspace} from "../../../types";
import {getCollection} from "../../../api/collection";
import {TreeView} from "@mui/lab";

type Props = {
    workspaces: Workspace[];
    onChange?: (selection: string[]) => void
}

type CollectionTreeProps = {
    collection: Collection;
    depth?: number,
}


function CollectionTree({collection, depth = 0}: CollectionTreeProps) {
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
        nodeId={collection['@id']}
        label={collection.title}
    >
        {tree && tree.map(c => <CollectionTree
            key={c.id}
            collection={c} depth={depth + 1} />)}
    </TreeItem>
}

export function CollectionsTreeView({workspaces, onChange}: Props) {
    const [expanded, setExpanded] = React.useState<string[]>([]);
    const [selected, setSelected] = React.useState<string[]>([]);

    const handleToggle = (event: React.ChangeEvent<{}>, nodeIds: string[]) => {
        setExpanded(nodeIds);
    };

    const handleSelect = (event: React.ChangeEvent<{}>, nodeIds: string[]) => {
        setSelected(nodeIds);
        onChange && onChange(nodeIds);
    };

    return <TreeView
        sx={{
            flexGrow: 1,
            maxWidth: 400,
        }}
        defaultCollapseIcon={<ExpandMoreIcon/>}
        defaultExpandIcon={<ChevronRightIcon/>}
        expanded={expanded}
        selected={selected}
        onNodeToggle={handleToggle}
        onNodeSelect={handleSelect}
        multiSelect
    >
        {workspaces.map(w => <TreeItem nodeId={w['@id']} label={w.name}>
            {w.collections.map(c => <CollectionTree collection={c} />)}
        </TreeItem>)}
    </TreeView>
}
