import {DefinitionBase, ListItemContainerProps} from './managerTypes.ts';
import {Checkbox, ListItem, ListItemButton} from '@mui/material';
import React from 'react';
import {stopPropagation} from '../../../../lib/stdFuncs.ts';

export default function ListItemContainer<D extends DefinitionBase>({
    item,
    selectedItem,
    handleItemClick,
    setSelection,
    selection,
    listComponent,
    onDelete,
}: ListItemContainerProps<D>) {
    return (
        <>
            <ListItem disablePadding>
                <ListItemButton
                    onClick={handleItemClick(item)}
                    selected={selectedItem?.id === item.id}
                >
                    {selection ? (
                        <div
                            onMouseDown={stopPropagation}
                            onClick={stopPropagation}
                        >
                            <Checkbox
                                checked={selection.includes(item.id)}
                                onChange={e => {
                                    if (e.target.checked) {
                                        setSelection(s => [...s, item.id]);
                                    } else {
                                        setSelection(s =>
                                            s.filter(id => id !== item.id)
                                        );
                                    }
                                }}
                            />
                        </div>
                    ) : null}
                    {React.createElement(listComponent, {
                        data: item,
                        key: item.id,
                        onEdit: handleItemClick(item, true),
                        onDelete: onDelete ? () => onDelete(item) : undefined,
                    })}
                </ListItemButton>
            </ListItem>
        </>
    );
}
