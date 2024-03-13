import {Button, ListItem, Skeleton} from "@mui/material";
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useBasketStore} from "../../store/basketStore.ts";
import {AppDialog} from '@alchemy/phrasea-ui';
import {Basket} from "../../types.ts";
import {useTranslation} from 'react-i18next';
import BasketMenuItem from "./BasketMenuItem.tsx";

type Props = {} & StackedModalProps;

export default function BasketListDialog({
    modalIndex,
    open,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const setCurrent = useBasketStore(state => state.setCurrent);
    const loading = useBasketStore(state => state.loading);
    const baskets = useBasketStore(state => state.baskets);

    const onSelect = (data: Basket): void => {
        setCurrent(data);
        closeModal();
    }

    return <AppDialog
        maxWidth={'sm'}
        modalIndex={modalIndex}
        open={open}
        loading={loading}
        onClose={closeModal}
        title={t('basket.choose_modal.title', 'Select current Basket')}
        actions={({onClose}) => (
            <>
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
        {!loading ? baskets.map(b => <BasketMenuItem
            key={b.id}
            onClick={() => onSelect(b)}
            noEdit={true}
            data={b}
        />) : <>
            <ListItem>
                <Skeleton variant={'text'} width={'100%'}/>
            </ListItem>
            <ListItem>
                <Skeleton variant={'text'} width={'100%'}/>
            </ListItem>
        </>}
    </AppDialog>
}
