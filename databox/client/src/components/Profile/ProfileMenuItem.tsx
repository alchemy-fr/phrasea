import {
    IconButton,
    ListItem,
    ListItemButton,
    ListItemProps,
    ListItemSecondaryAction,
    ListItemText,
} from '@mui/material';
import {DisplayProfile} from '../../types';
import {useTranslation} from 'react-i18next';
import {Classes} from '../../classes.ts';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import SyncIcon from '@mui/icons-material/Sync';

type Props = {
    data: DisplayProfile;
    selected?: boolean;
    syncData?: () => void;
    onClick?: () => void;
    onDelete: (id: string) => void;
    onEdit: (id: string) => void;
} & Pick<ListItemProps, 'onContextMenu'>;

export default function ProfileMenuItem({
    data,
    onClick,
    selected,
    onContextMenu,
    onDelete,
    onEdit,
    syncData,
}: Props) {
    const {t} = useTranslation();

    const canEdit = data.capabilities.edit;
    const canDelete = data.capabilities.delete;

    const textSx = {
        pr: (canEdit ? 6 : 0) + (canDelete ? 6 : 0),
    };

    return (
        <>
            <ListItem onContextMenu={onContextMenu} disablePadding>
                <ListItemButton
                    selected={selected}
                    role={undefined}
                    onClick={onClick}
                >
                    <ListItemText
                        className={Classes.ellipsisText}
                        primary={data.name}
                        secondary={data.description}
                        secondaryTypographyProps={{
                            style: {whiteSpace: 'normal'},
                            sx: textSx,
                        }}
                        primaryTypographyProps={{
                            sx: textSx,
                        }}
                    />
                    <ListItemSecondaryAction>
                        {syncData ? (
                            <IconButton
                                onMouseDown={e => e.stopPropagation()}
                                onClick={e => {
                                    e.stopPropagation();
                                    syncData();
                                }}
                            >
                                <SyncIcon />
                            </IconButton>
                        ) : null}
                        {canEdit && (
                            <IconButton
                                onMouseDown={e => e.stopPropagation()}
                                onClick={e => {
                                    e.stopPropagation();
                                    onEdit(data.id);
                                }}
                            >
                                <EditIcon />
                            </IconButton>
                        )}
                        {canDelete && (
                            <IconButton
                                onMouseDown={e => e.stopPropagation()}
                                onClick={e => {
                                    e.stopPropagation();
                                    if (
                                        window.confirm(
                                            t(
                                                'display_profile.delete.confirm',
                                                'Are you sure you want to delete this Display Profile?'
                                            )
                                        )
                                    ) {
                                        onDelete(data.id);
                                    }
                                }}
                            >
                                <DeleteIcon />
                            </IconButton>
                        )}
                    </ListItemSecondaryAction>
                </ListItemButton>
            </ListItem>
        </>
    );
}
