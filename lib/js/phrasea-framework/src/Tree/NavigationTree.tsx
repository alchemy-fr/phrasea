import {NavigationTreeProps} from './types';
import {List} from '@mui/material';
import TreeNode from './TreeNode';

export default function NavigationTree<D extends {}>({
    rootItem,
    renderItem,
}: NavigationTreeProps<D>) {
    return (
        <List>
            {rootItem.children?.map(item => (
                <TreeNode level={0} key={item.id} item={item} renderItem={renderItem} />
            ))}
        </List>
    );
}
