import {Basket} from '../../../types';
import {DialogTabProps} from '../Tabbed/TabbedDialog';
import ContentTab from '../Tabbed/ContentTab';
import {Alert, Button, Typography} from '@mui/material';
import {useTranslation} from 'react-i18next';
import ConfirmDialog from '../../Ui/ConfirmDialog';
import {useModals} from '@alchemy/navigation';
import {useBasketStore} from "../../../store/basketStore.ts";

type Props = {
    data: Basket;
} & DialogTabProps;

export default function Operations({data, onClose, minHeight}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();

    const deleteBasket = useBasketStore(state => state.deleteBasket);

    const deleteConfirm = async () => {
        openModal(ConfirmDialog, {
            textToType: data.title,
            title: t(
                'basket_delete.confirm',
                'Are you sure you want to delete this basket?'
            ),
            onConfirm: async () => {
                await deleteBasket(data.id);
            },
            onConfirmed: () => {
                onClose();
            },
        });
    };
    return (
        <ContentTab onClose={onClose} minHeight={minHeight}>
            <Alert
                color={'error'}
                sx={{
                    mb: 2,
                }}
            >
                {t('danger_zone', 'Danger zone')}
            </Alert>
            <Typography variant={'h2'} sx={{mb: 1}}>
                {t('basket_delete.title', 'Delete Basket')}
            </Typography>
            <Button onClick={deleteConfirm} color={'error'}>
                {t('basket_delete.title', 'Delete Basket')}
            </Button>
        </ContentTab>
    );
}
