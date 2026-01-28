import {ListItemIcon, MenuItem} from '@mui/material';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import ContextMenu from '../Ui/ContextMenu.tsx';
import React from 'react';
import {
    ContextMenuContext,
    useContextMenu,
} from '../../hooks/useContextMenu.ts';
import {Basket} from '../../types.ts';
import {useTranslation} from 'react-i18next';

type Props = {
    onEdit: (data: Basket) => void;
    onDelete: (data: Basket) => void;
    contextMenu: ContextMenuContext<Basket>;
} & ReturnType<typeof useContextMenu<Basket>>;

export default function BasketContextMenu({
    contextMenu,
    onContextMenuClose,
    onEdit,
    onDelete,
}: Props) {
    const {t} = useTranslation();

    return (
        <>
            <ContextMenu
                onClose={onContextMenuClose}
                contextMenu={contextMenu}
                id={'basket-context-menu'}
            >
                <MenuItem
                    disabled={!contextMenu.data.capabilities.canEdit}
                    onClick={() => onEdit(contextMenu.data)}
                >
                    <ListItemIcon>
                        <EditIcon />
                    </ListItemIcon>
                    {t('basket.actions.edit', 'Edit Basket')}
                </MenuItem>
                <MenuItem
                    disabled={!contextMenu.data.capabilities.canDelete}
                    onClick={() => onDelete(contextMenu.data)}
                >
                    <ListItemIcon>
                        <DeleteIcon />
                    </ListItemIcon>
                    {t('basket.actions.delete', 'Delete Basket')}
                </MenuItem>
            </ContextMenu>
        </>
    );
}
