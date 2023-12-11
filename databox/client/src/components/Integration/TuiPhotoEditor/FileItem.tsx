import React, {MouseEvent as ReactMouseEvent} from 'react';
import {
    Divider,
    IconButton,
    ListItemButton,
    ListItemIcon,
    ListItemSecondaryAction,
    ListItemText,
    Menu,
    MenuItem,
} from '@mui/material';
import FileOpenIcon from '@mui/icons-material/FileOpen';
import SaveAsButton from '../../Media/Asset/Actions/SaveAsButton';
import DeleteIcon from '@mui/icons-material/Delete';
import {Asset, File, IntegrationData} from '../../../types';
import MoreHorizIcon from '@mui/icons-material/MoreHoriz';

type Props = {
    asset: Asset;
    data: IntegrationData;
    disabled: boolean;
    selected: boolean;
    onOpen: (file: File, name: string | null) => void;
    onDelete: (id: string) => Promise<void>;
};

export default function FileItem({
    asset,
    data,
    disabled,
    selected,
    onOpen,
    onDelete,
}: Props) {
    const [anchorEl, setAnchorEl] = React.useState<null | HTMLElement>(null);
    const open = Boolean(anchorEl);
    const [deleting, setDeleting] = React.useState(false);
    const handleClick = (event: React.MouseEvent<HTMLElement>) => {
        setAnchorEl(event.currentTarget);
    };
    const handleClose = React.useCallback(() => {
        setAnchorEl(null);
    }, []);

    const onClick = React.useCallback(() => {
        onOpen(data.value, data.keyId);
    }, [data]);

    const deleteHandler = React.useCallback(
        async (e: ReactMouseEvent<HTMLElement, MouseEvent>) => {
            handleClose();
            e.stopPropagation();
            setDeleting(true);
            await onDelete(data.id);
            setDeleting(false);
        },
        [handleClose, onDelete, data.id]
    );

    return (
        <ListItemButton
            disabled={deleting || disabled}
            selected={selected}
            key={data.id}
            onClick={onClick}
        >
            <ListItemIcon>
                <FileOpenIcon />
            </ListItemIcon>
            <ListItemText>{data.keyId}</ListItemText>

            <ListItemSecondaryAction>
                <IconButton onClick={handleClick} disabled={deleting}>
                    <MoreHorizIcon />
                </IconButton>
            </ListItemSecondaryAction>

            <Menu anchorEl={anchorEl} open={open} onClose={handleClose}>
                <MenuItem onClick={deleteHandler}>
                    <ListItemIcon>
                        <DeleteIcon />
                    </ListItemIcon>
                    <ListItemText>Delete</ListItemText>
                </MenuItem>

                <Divider />

                <SaveAsButton
                    Component={MenuItem}
                    asset={asset}
                    file={data.value}
                    suggestedTitle={asset.resolvedTitle + ' - ' + data.keyId}
                />
            </Menu>
        </ListItemButton>
    );
}
