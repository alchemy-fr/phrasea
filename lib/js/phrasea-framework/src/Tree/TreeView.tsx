import {
    OnNodeAdd,
    OnToggleExpand,
    OnToggleSelectNode,
    TreeBaseItem,
    TreeViewClasses,
    TreeViewProps,
} from './types';
import {List} from '@mui/material';
import BaseTreeNode from './BaseTreeNode';
import {useCallback, useMemo, useState} from 'react';
import {getFlattenNodes} from './treeHelper';

export default function TreeView<D extends TreeBaseItem>({
    nodes,
    renderNodeLabel,
    loadChildren,
    onToggleSelect,
    onToggleExpand,
    selectShouldCollapse,
    selectShouldExpand = true,
    collapseShouldUnselectChildren = true,
    defaultExpandedNodes = [],
    defaultSelectedNodes = [],
    disabledBranches,
    required,
    multiple = false,
    onSelectionChange,
    ...nodeProps
}: TreeViewProps<D>) {
    const [expandedNodes, setExpandedNodes] =
        useState<string[]>(defaultExpandedNodes);
    const [selectedNodes, setSelectedNodes] =
        useState<string[]>(defaultSelectedNodes);

    const onToggleExpandInternal = useCallback<OnToggleExpand<D>>(
        async (node, expanded) => {
            if (
                expanded &&
                node.hasChildren &&
                (node.children === undefined || false === node.childrenLoaded)
            ) {
                await loadChildren?.(node);
            }

            if (!expanded && collapseShouldUnselectChildren) {
                const allChildren = getFlattenNodes(nodes);
                const idsToUnselect = allChildren.map(node => node.id);
                setSelectedNodes(prev =>
                    prev.filter(id => !idsToUnselect.includes(id))
                );

                if (onToggleSelect) {
                    allChildren.forEach(n => onToggleSelect(n, false));
                }
            }

            await onToggleExpand?.(node, expanded);

            setExpandedNodes(prev => {
                if (expanded) {
                    return [...prev, node.id];
                } else {
                    return prev.filter(id => id !== node.id);
                }
            });
        },
        [setExpandedNodes, onToggleExpand, loadChildren]
    );

    const onToggleSelectInternal = useCallback<OnToggleSelectNode<D>>(
        (node, selected) => {
            if (
                selected &&
                selectShouldExpand &&
                !expandedNodes.includes(node.id)
            ) {
                onToggleExpandInternal(node, true);
            } else if (
                !selected &&
                selectShouldCollapse &&
                expandedNodes.includes(node.id)
            ) {
                onToggleExpandInternal(node, false);
            }

            onToggleSelect?.(node, selected);
            if (onSelectionChange) {
                if (multiple) {
                    onSelectionChange(selected ? [...selectedNodes, node.id] : selectedNodes.filter(id => id !== node.id));
                } else {
                    onSelectionChange(selected ? [node.id] : []);
                }
            }

            setSelectedNodes(prev => {
                if (multiple) {
                    if (selected) {
                        return [...prev, node.id];
                    } else {
                        return prev.filter(id => id !== node.id);
                    }
                } else {
                    if (required && !selected) {
                        return prev;
                    }

                    return selected ? [node.id] : [];
                }
            });
        },
        [setSelectedNodes, onToggleSelect, onToggleExpandInternal]
    );

    return (
        <List
            dense={true}
            sx={{
                [`.${TreeViewClasses.Node}`]: {
                    'display': 'flex',
                    'flexDirection': 'row',
                    'gap': 1,
                    'alignItems': 'center',
                    'borderRadius': 1,
                    '& .MuiSvgIcon-root': {
                        fontSize: 20,
                    },
                },
                [`.${TreeViewClasses.NodeArrow}`]: {
                    width: 24,
                    display: 'flex',
                    alignItems: 'center',
                    ml: -1,
                    mr: -0.5,
                    cursor: 'pointer',
                    transition: 'transform 0.2s ease-in-out',
                    transform: 'rotate(-90deg)',
                },
                [`.${TreeViewClasses.NodeExpanded}`]: {
                    [`.${TreeViewClasses.NodeArrow}`]: {
                        transform: 'rotate(0deg)',
                    },
                },
                [`.${TreeViewClasses.NodeChildren}`]: {
                    ml: 2.5,
                    borderLeft: '1px dashed rgba(0, 0, 0, 0.12)',
                },
                [`.${TreeViewClasses.NodeLabel}`]: {
                    flexGrow: 1,
                },
            }}
        >
            {nodes.map(node => (
                <BaseTreeNode<D>
                    {...nodeProps}
                    onToggleSelect={onToggleSelectInternal}
                    onToggleExpand={onToggleExpandInternal}
                    level={0}
                    key={node.id}
                    expandedNodes={expandedNodes}
                    selectedNodes={selectedNodes}
                    node={node}
                    renderNodeLabel={renderNodeLabel}
                    disabledBranches={disabledBranches}
                />
            ))}
        </List>
    );
}
