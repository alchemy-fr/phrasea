import {
    IconButton,
    ListItem,
    ListItemButton,
    ListItemText,
} from '@mui/material';
import ModalLink from '../Routing/ModalLink.tsx';
import {modalRoutes} from '../../routes.ts';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import {Basket} from '../../types.ts';
import {useTranslation} from 'react-i18next';

type Props = {
    data: Basket;
    onDelete?: (data: Basket) => void;
    noEdit?: boolean;
    selected?: boolean;
    onClick?: () => void;
};

export default function BasketMenuItem({
    data,
    onDelete,
    noEdit,
    onClick,
    selected,
}: Props) {
    const {t} = useTranslation();

    return (
        <>
            <ListItem
                secondaryAction={
                    <>
                        <span className="c-action">
                            {!noEdit && data.capabilities.canEdit ? (
                                <IconButton
                                    component={ModalLink}
                                    route={modalRoutes.baskets.routes.manage}
                                    params={{
                                        id: data.id,
                                        tab: 'edit',
                                    }}
                                    title={t(
                                        'basket.item.edit',
                                        'Edit this basket'
                                    )}
                                    aria-label="edit"
                                >
                                    <EditIcon />
                                </IconButton>
                            ) : null}
                            {onDelete && data.capabilities.canDelete ? (
                                <IconButton
                                    onClick={e => {
                                        e.stopPropagation();
                                        onDelete(data);
                                    }}
                                    aria-label="delete"
                                >
                                    <DeleteIcon />
                                </IconButton>
                            ) : null}
                        </span>
                    </>
                }
                disablePadding
            >
                <ListItemButton
                    selected={selected}
                    role={undefined}
                    onClick={onClick}
                >
                    <ListItemText
                        primary={
                            data.titleHighlight ||
                            data.title ||
                            t('basket.default.title', 'My Basket')
                        }
                    />
                </ListItemButton>
            </ListItem>
        </>
    );
}
