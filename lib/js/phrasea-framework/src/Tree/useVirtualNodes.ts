import {useCallback, useMemo, useState} from 'react';
import {OnNodeAdd, TreeBaseItem, TreeNode, VirtualNodes} from './types';

type Props<D extends TreeBaseItem> = {
    nodes: TreeNode<D>[];
    newItem?: D;
};

export function useVirtualNodes<D extends TreeBaseItem>({newItem, nodes}: Props<D>) {
    const [virtualNodes, setVirtualNodes] = useState<VirtualNodes<D>>({});

    const createNode = useCallback<OnNodeAdd<D>>(
        (parentNode, node) => {
            setVirtualNodes(prev => {
                const n = {...prev};

                n[parentNode.id] ??= [];
                n[parentNode.id].push({
                    ...node,
                    id: `${parentNode.id}/virtual-${Date.now()}`,
                    virtual: true,
                    data: {
                        ...(newItem ?? ({} as D)),
                        ...node.data,
                    },
                } as TreeNode<D>);

                return n;
            });
        },
        [setVirtualNodes]
    );

    const normalizedNodes = useMemo<TreeNode<D>[]>(() => {
        if (Object.keys(virtualNodes).length === 0) {
            return nodes;
        }

        const insertVirtualNodes = (node: TreeNode<D>): TreeNode<D> => {
            const virtualChildren = virtualNodes[node.id] || [];

            const normalizedChildren = [...(node.children || []), ...virtualChildren].map(
                insertVirtualNodes
            );

            return {
                ...node,
                children: normalizedChildren,
                hasChildren: normalizedChildren.length > 0,
            };
        };

        return nodes.map(n => insertVirtualNodes(n));
    }, [nodes, virtualNodes]);

    return {
        createNode,
        virtualNodes,
        setVirtualNodes,
        normalizedNodes,
    };
}
