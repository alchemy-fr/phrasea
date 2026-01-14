import {TreeBaseItem, TreeNodeProps, TreeViewClasses} from './types';
import TreeNodeChildren from './TreeNodeChildren';
import {
    CircularProgress,
    Collapse,
    IconButton,
    ListItemButton,
} from '@mui/material';
import classNames from 'classnames';
import {useState} from 'react';
import Box from '@mui/material/Box';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import CreateNewFolderIcon from '@mui/icons-material/CreateNewFolder';

export default function BaseTreeNode<D extends TreeBaseItem>(
    props: TreeNodeProps<D>
) {
    const [editing, setEditing] = useState(false);
    const {
        node,
        renderNodeLabel,
        level,
        onToggleSelect,
        onToggleExpand,
        selectedNodes,
        expandedNodes,
        onNodeAdd,
    } = props;

    const selected = selectedNodes.includes(node.id);
    const expanded = expandedNodes.includes(node.id);
    const disabled = props.disabledBranches?.some(branchId =>
        node.id.startsWith(branchId)
    );

    const [expanding, setExpanding] = useState(false);

    return (
        <>
            <ListItemButton
                className={classNames({
                    [TreeViewClasses.Node]: true,
                    [TreeViewClasses.NodeSelected]: selected,
                    [TreeViewClasses.NodeExpanded]: expanded,
                    [TreeViewClasses.NodeDisabled]: disabled,
                })}
                disabled={disabled}
                selected={selected}
                onClick={() => onToggleSelect(node, !selected)}
            >
                <div
                    className={TreeViewClasses.NodeArrow}
                    onClick={async e => {
                        e.stopPropagation();

                        setExpanding(true);
                        try {
                            await onToggleExpand(node, !expanded);
                        } finally {
                            setExpanding(false);
                        }
                    }}
                    onMouseDown={e => e.stopPropagation()}
                >
                    {node.hasChildren ? (
                        expanding ? (
                            <CircularProgress size={15} />
                        ) : (
                            <ExpandMoreIcon />
                        )
                    ) : null}
                </div>
                <div className={TreeViewClasses.NodeLabel}>
                    {renderNodeLabel({
                        level,
                        node,
                    })}
                </div>
                <div>
                    {!editing && node.canEdit ? (
                        <IconButton
                            onClick={() => setEditing(true)}
                            onMouseDown={e => e.stopPropagation()}
                        >
                            <EditIcon />
                        </IconButton>
                    ) : null}
                    {!editing && node.canDelete ? (
                        <IconButton
                            onClick={() => setEditing(true)}
                            onMouseDown={e => e.stopPropagation()}
                        >
                            <DeleteIcon />
                        </IconButton>
                    ) : null}
                    {onNodeAdd && !editing && node.canAddChildren ? (
                        <IconButton
                            onClick={e => {
                                e.stopPropagation();
                                onNodeAdd(node, {});
                            }}
                            onMouseDown={e => e.stopPropagation()}
                        >
                            <CreateNewFolderIcon />
                        </IconButton>
                    ) : null}
                </div>
            </ListItemButton>
            <Collapse in={expanded} timeout="auto" unmountOnExit>
                <Box
                    className={TreeViewClasses.NodeChildren}
                    sx={{
                        pl: 1 + level / 4,
                    }}
                >
                    <TreeNodeChildren {...props} level={level + 1} />
                </Box>
            </Collapse>
        </>
    );
}
