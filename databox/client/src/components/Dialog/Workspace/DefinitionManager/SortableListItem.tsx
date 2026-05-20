import {
    SortableItem,
    SortableItemProps,
} from '../../../Ui/Sortable/SortableList.tsx';
import React from 'react';
import {DefinitionBase, SortableListItemProps} from './managerTypes.ts';
import ListItemContainer from './ListItemContainer.tsx';

export const SortableListItem = React.memo(
    <D extends SortableItem & DefinitionBase>({
        data,
        itemProps,
    }: {
        itemProps: SortableListItemProps<D>;
    } & SortableItemProps<D>) => {
        return <ListItemContainer item={data} {...itemProps} />;
    }
);
