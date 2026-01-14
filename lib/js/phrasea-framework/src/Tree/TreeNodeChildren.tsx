import {TreeNodeProps} from './types';
import BaseTreeNode from './BaseTreeNode';

export default function TreeNodeChildren<D extends {}>(
    props: TreeNodeProps<D>
) {
    const {node, selectedNodes, expandedNodes} = props;

    return (
        <>
            {node.children
                ? node.children.map(child => (
                      <BaseTreeNode<D>
                          {...props}
                          key={child.id}
                          node={child}
                      />
                  ))
                : null}
        </>
    );
}
