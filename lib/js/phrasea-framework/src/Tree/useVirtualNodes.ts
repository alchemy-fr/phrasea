import React, {useCallback, useMemo, useState} from 'react';
import {
    EditionProps,
    OnNodeAdd,
    OnNodeRemove, OnNodeStartEdit,
    OnNodeUpdate,
    TreeBaseItem,
    TreeNode,
    VirtualNodes,
    VirtualTreeNode,
} from './types';

type Props<D extends TreeBaseItem> = {
    nodes: TreeNode<D>[];
    newItem?: (parentNode?: TreeNode<D>) => D;
};

type Return<D extends TreeBaseItem> = {
    normalizedNodes: TreeNode<D>[];
    virtualNodes: VirtualNodes<D>;
    setVirtualNodes: React.Dispatch<
        React.SetStateAction<VirtualNodes<D>>
    >;
} & EditionProps<D>;

export function useVirtualNodes<D extends TreeBaseItem>({
    newItem,
    nodes,
}: Props<D>): Return<D> {
    const [virtualNodes, setVirtualNodes] = useState<VirtualNodes<D>>([]);

    const onNodeAdd = useCallback<OnNodeAdd<D>>(
        (parentNode, node) => {
            setVirtualNodes(prev => {
                return prev.concat([
                    {
                        parentId: parentNode.id,
                        parentNode,
                        hasChildren: false,
                        loadingChildren: false,
                        canDelete: true,
                        canEdit: true,
                        canAddChildren: true,
                        editing: true,
                        ...node,
                        id: `${parentNode.id}/virtual-${Date.now()}`,
                        virtual: true,
                        data: {
                            ...(newItem?.(parentNode) ?? ({} as D)),
                            ...node.data,
                        },
                    } as VirtualTreeNode<D>,
                ]);
            });
        },
        [setVirtualNodes]
    );

    const onNodeRemove = useCallback<OnNodeRemove<D>>(
        (node) => {
            setVirtualNodes(prev => prev.filter(n => n.id !== node.id));
        },
        [setVirtualNodes]
    );

    const onNodeUpdate = useCallback<OnNodeUpdate<D>>(
        (oldNode, newNode) => {
            setVirtualNodes(prev =>
                prev.map(n => {
                    if (n.id === oldNode.id) {
                        return {
                            ...n,
                            ...newNode,
                            data: {
                                ...n.data,
                                ...(newNode.data ?? {}),
                            },
                            editing: false,
                            editedOnce: true,
                        };
                    }

                    return n;
                })
            );
        },
        [setVirtualNodes]
    );

    const nodeToggleEdit = useCallback((node: TreeNode<D>, editing: boolean) => {
        setVirtualNodes(prev =>
            prev.map(n => {
                if (n.id === node.id) {
                    return {
                        ...n,
                        editing,
                    };
                }

                return n;
            })
        );
    }, [setVirtualNodes]);

    const onNodeStartEdit = useCallback<OnNodeStartEdit<D>>(node => {
        nodeToggleEdit(node, true);
    }, [nodeToggleEdit]);

    const onNodeCancelEdit = useCallback<OnNodeStartEdit<D>>(node => {
        nodeToggleEdit(node, false);
    }, [nodeToggleEdit]);

    const normalizedNodes = useMemo<TreeNode<D>[]>(() => {
        if (virtualNodes.length === 0) {
            return nodes;
        }

        const insertVirtualNodes = (node: TreeNode<D>): TreeNode<D> => {
            const virtualChildren = virtualNodes.filter(
                vn => vn.parentId === node.id
            );

            if (virtualChildren.length === 0 && !node.children) {
                return node;
            }

            const normalizedChildren = [
                ...(node.children ?? []),
                ...virtualChildren,
            ].map(insertVirtualNodes);

            return {
                ...node,
                children: normalizedChildren,
                hasChildren: normalizedChildren.length > 0,
            };
        };

        return nodes.map(n => insertVirtualNodes(n));
    }, [nodes, virtualNodes]);

    return {
        onNodeAdd,
        onNodeRemove,
        onNodeUpdate,
        onNodeCancelEdit,
        onNodeStartEdit,
        virtualNodes,
        setVirtualNodes,
        normalizedNodes,
    };
}
