import {TreeBaseItem, TreeNode} from './types';

export function getAllTreeNodeIds<D extends TreeBaseItem>(nodes: TreeNode<D>[]): string[] {
    return getFlattenNodes(nodes).map(node => node.id);
}

export function getFlattenNodes<D extends TreeBaseItem>(
    nodes: TreeNode<D>[]
): TreeNode<D>[] {
    const flattenNodes: TreeNode<D>[] = [];
    const collectIds = (nodeList: TreeNode<D>[]) => {
        for (const node of nodeList) {
            flattenNodes.push(node);
            if (node.children) {
                collectIds(node.children);
            }
        }
    };

    collectIds(nodes);

    return flattenNodes;
}

export function findNodeById<D extends TreeBaseItem>(nodes: TreeNode<D>[], id: string): TreeNode<D> {
    const findNode = (nodesList: TreeNode<D>[]): TreeNode<D> | undefined => {
        const found = nodesList.find(n => n.id === id);
        if (found) {
            return found;
        }

        for (const n of nodesList) {
            if (n.children) {
                const childResult = findNode(n.children);
                if (childResult) {
                    return childResult;
                }
            }
        }

        return undefined;
    };

    return findNode(nodes)!;
}
