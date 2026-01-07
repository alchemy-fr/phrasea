import {TreeNodeProps} from './types';
import TreeNode from './TreeNode';

export default function TreeNodeChildren<D extends {}>(
    props: TreeNodeProps<D>
) {
    const {item, renderItem} = props;

    return (
        <>
            {item.children
                ? item.children.map(child => (
                      <TreeNode<D> {...props} key={child.id} item={child} />
                  ))
                : null}
        </>
    );
}
