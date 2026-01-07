import {TreeNodeProps} from './types';
import TreeNodeChildren from './TreeNodeChildren';

export default function TreeNode<D extends {}>(props: TreeNodeProps<D>) {
    const {item, renderItem, level} = props;
    return (
        <>
            {renderItem({
                data: item.data,
                level,
                item,
            })}
            <TreeNodeChildren {...props} level={level + 1} />
        </>
    );
}
