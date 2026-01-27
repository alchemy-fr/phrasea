import {Button, List} from '@mui/material';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useBasketStore} from '../../store/basketStore';
import {AppDialog, LoadMoreRow} from '@alchemy/phrasea-ui';
import {Basket} from '../../types';
import {useTranslation} from 'react-i18next';
import BasketMenuItem from './BasketMenuItem';
import AddIcon from '@mui/icons-material/Add';
import {useBasketList} from '../../hooks/useBasketList.ts';
import BasketSkeleton from './BasketSkeleton.tsx';
import React from 'react';
import SearchBar from '../Ui/SearchBar.tsx';

type Props = {} & StackedModalProps;

export default function BasketListDialog({modalIndex, open}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const setCurrent = useBasketStore(state => state.setCurrent);

    const onSelect = (data: Basket): void => {
        setCurrent(data);
        closeModal();
    };

    const {
        createBasket,
        loading,
        searchQuery,
        setSearchQuery,
        baskets,
        searchResult,
        loadMoreHandler,
        hasLoadMore,
        searchHandler,
    } = useBasketList({
        onBasketCreate: data => {
            onSelect(data);
        },
    });

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
            <SearchBar
                name={'basket-search'}
                searchQuery={searchQuery}
                setSearchQuery={setSearchQuery}
                loading={searchResult.loading}
                searchHandler={searchHandler}
            />
            <List>
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
                    <BasketSkeleton />
                )}
            </List>

            <LoadMoreRow
                hasMore={hasLoadMore}
                loading={loading}
                onClick={loadMoreHandler}
            />
        </AppDialog>
    );
}
