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
