import {TreeBaseItem, TreeNodeProps, TreeViewClasses} from './types';
import TreeNodeChildren from './TreeNodeChildren';
import {
    CircularProgress,
    Collapse,
    IconButton,
    ListItemButton,
} from '@mui/material';
import classNames from 'classnames';
import React, {useState} from 'react';
import Box from '@mui/material/Box';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import CreateNewFolderIcon from '@mui/icons-material/CreateNewFolder';

export default function BaseTreeNode<D extends TreeBaseItem>(
    props: TreeNodeProps<D>
) {
    const {
        node,
        renderNodeLabel,
        level,
        onToggleSelect,
        onToggleExpand,
        selectedNodes,
        expandedNodes,
        onNodeAdd,
        onNodeRemove,
        onNodeUpdate,
        editNodeComponent,
        onNodeStartEdit,
        onNodeCancelEdit,
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
                    {editNodeComponent && node.editing
                        ? React.createElement(editNodeComponent, {
                              ...props,
                              onFinishEdit: (data: D) => {
                                  onNodeUpdate?.(node, {
                                        ...node,
                                        data,
                                  });
                              },
                              onCancelEdit: () => {
                                  if (onNodeRemove && !node.editedOnce) {
                                      onNodeRemove?.(node);
                                  } else {
                                    onNodeCancelEdit?.(node);
                                  }
                              },
                          })
                        : renderNodeLabel({
                              level,
                              node,
                          })}
                </div>
                <div>
                    {editNodeComponent &&
                    onNodeStartEdit &&
                    !node.editing &&
                    node.canEdit ? (
                        <IconButton
                            onClick={() => onNodeStartEdit(node)}
                            onMouseDown={e => e.stopPropagation()}
                        >
                            <EditIcon />
                        </IconButton>
                    ) : null}
                    {onNodeRemove && !node.editing && node.canDelete ? (
                        <IconButton
                            onClick={() => onNodeRemove(node)}
                            onMouseDown={e => e.stopPropagation()}
                        >
                            <DeleteIcon />
                        </IconButton>
                    ) : null}
                    {onNodeAdd && !node.editing && node.canAddChildren ? (
                        <IconButton
                            onClick={e => {
                                e.stopPropagation();
                                onNodeAdd(node, {});
                                onToggleExpand(node, true);
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
