import {
    IconButton,
    ListItem,
    ListItemButton,
    ListItemProps,
    ListItemSecondaryAction,
    ListItemText,
} from '@mui/material';
import {AttributeList} from '../../types';
import {useTranslation} from 'react-i18next';
import {Classes} from '../../classes.ts';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';

type Props = {
    data: AttributeList;
    selected?: boolean;
    onClick?: () => void;
    onDelete: (id: string) => void;
    onEdit: (id: string) => void;
} & Pick<ListItemProps, 'onContextMenu'>;

export default function AttributeListMenuItem({
    data,
    onClick,
    selected,
    onContextMenu,
    onDelete,
    onEdit,
}: Props) {
    const {t} = useTranslation();

    const canEdit = data.capabilities.canEdit;
    const canDelete = data.capabilities.canDelete;

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
                        primary={
                            data.title ||
                            t(
                                'attributeList.default.title',
                                'My Attribute List'
                            )
                        }
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
                                                'attributeList.delete.confirm',
                                                'Are you sure you want to delete this attribute list?'
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
