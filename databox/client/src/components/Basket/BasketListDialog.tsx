import {Button, ListItem, Skeleton} from '@mui/material';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useBasketStore} from '../../store/basketStore';
import {AppDialog} from '@alchemy/phrasea-ui';
import {Basket} from '../../types';
import {useTranslation} from 'react-i18next';
import BasketMenuItem from './BasketMenuItem';
import CreateBasket from './CreateBasket';
import AddIcon from '@mui/icons-material/Add';

type Props = {} & StackedModalProps;

export default function BasketListDialog({modalIndex, open}: Props) {
    const {t} = useTranslation();
    const {openModal, closeModal} = useModals();

    const setCurrent = useBasketStore(state => state.setCurrent);
    const loading = useBasketStore(state => state.loading);
    const baskets = useBasketStore(state => state.baskets);

    const onSelect = (data: Basket): void => {
        setCurrent(data);
        closeModal();
    };

    const createBasket = () => {
        openModal(CreateBasket, {
            onCreate: data => {
                onSelect(data);
            },
        });
    };

    return (
        <AppDialog
            maxWidth={'sm'}
            modalIndex={modalIndex}
            open={open}
            loading={loading}
            onClose={closeModal}
            title={t('basket.choose_modal.title', 'Select current Basket')}
            actions={({onClose}) => (
                <>
                    <Button
                        variant={'contained'}
                        onClick={createBasket}
                        startIcon={<AddIcon />}
                    >
                        {t('basket.create_button.label', 'Create new Basket')}
                    </Button>
                    <Button
                        onClick={onClose}
                        color={'warning'}
                        disabled={loading}
                    >
                        {t('dialog.cancel', 'Cancel')}
                    </Button>
                </>
            )}
        >
            {!loading ? (
                baskets.map(b => (
                    <BasketMenuItem
                        key={b.id}
                        disabled={!b.capabilities.canEdit}
                        onClick={
                            b.capabilities.canEdit
                                ? () => onSelect(b)
                                : undefined
                        }
                        data={b}
                    />
                ))
            ) : (
                <>
                    <ListItem>
                        <Skeleton variant={'text'} width={'100%'} />
                    </ListItem>
                    <ListItem>
                        <Skeleton variant={'text'} width={'100%'} />
                    </ListItem>
                </>
            )}
        </AppDialog>
    );
}
