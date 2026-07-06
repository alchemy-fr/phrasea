import {useBasketStore} from '../store/basketStore.ts';
import {useNavigateToModal} from '../components/Routing/ModalLink.tsx';
import {useSearch} from './useSearch.ts';
import {getBaskets} from '../api/basket.ts';
import {Basket} from '../types.ts';
import {toast} from 'react-toastify';
import {useTranslation} from 'react-i18next';
import {useModals} from '@alchemy/navigation';
import CreateBasket from '../components/Basket/CreateBasket.tsx';
import {useContextMenu} from './useContextMenu.ts';
import {ConfirmDialog} from '@alchemy/phrasea-framework';
import {modalRoutes} from '../routes.ts';

type Props = {
    onBasketCreate?: (data: Basket) => void;
};

export function useBasketList({onBasketCreate}: Props = {}) {
    const {contextMenu, onContextMenuOpen, onContextMenuClose} =
        useContextMenu<Basket>();
    const {t} = useTranslation();
    const baskets = useBasketStore(state => state.baskets);
    const loading = useBasketStore(state => state.loading);
    const loadMore = useBasketStore(state => state.loadMore);
    const hasMore = useBasketStore(state => state.hasMore);
    const load = useBasketStore(state => state.load);
    const deleteBasket = useBasketStore(state => state.deleteBasket);
    const archiveBasket = useBasketStore(state => state.archiveBasket);
    const unarchiveBasket = useBasketStore(state => state.unarchiveBasket);
    const {openModal} = useModals();
    const navigateToModal = useNavigateToModal();

    const onDelete = (data: Basket): void => {
        onContextMenuClose();
        openModal(ConfirmDialog, {
            textToType: data.name,

            title: t(
                'basket_delete.confirm',
                'Are you sure you want to delete this basket?'
            ),
            onConfirm: async () => {
                await deleteBasket(data.id);
                toast.success(
                    t(
                        'delete.basket.confirmed',
                        'Basket has been removed!'
                    ) as string
                );
            },
        });
    };

    const createBasket = () => {
        openModal(CreateBasket, {
            onCreate: onBasketCreate,
        });
    };

    const onEdit = (data: Basket) => {
        onContextMenuClose();
        navigateToModal(modalRoutes.baskets.routes.manage, {
            id: data.id,
            tab: 'edit',
        });
    };

    const onArchive = (data: Basket): void => {
        onContextMenuClose();
        archiveBasket(data.id, searchQueryOptions.displayArchived || false);
        toast.success(
            t('archive.basket.confirmed', 'Basket has been archived!') as string
        );
    };

    const onUnarchive = (data: Basket): void => {
        onContextMenuClose();
        unarchiveBasket(data.id);
        toast.success(
            t(
                'archive.basket.confirmed',
                'Basket has been unarchived!'
            ) as string
        );
    };

    const {
        searchQuery,
        setSearchQuery,
        searchQueryOptions,
        setSearchQueryOptions,
        results,
        searchResult,
        loadMoreHandler,
        hasMore: hasLoadMore,
        searchHandler,
    } = useSearch({
        items: baskets,
        loadItems: options => load({...options, displayArchived: false}),
        hasMore: hasMore(),
        loadMore: loadMore,
        search: (q, nextUrl, options) =>
            getBaskets({nextUrl, query: q, ...options}),
    });

    return {
        onEdit,
        onArchive,
        onUnarchive,
        onDelete,
        searchQuery,
        setSearchQuery,
        searchQueryOptions,
        setSearchQueryOptions,
        searchHandler,
        baskets: results,
        searchResult,
        loading,
        hasLoadMore,
        loadMoreHandler,
        createBasket,
        contextMenu,
        onContextMenuOpen,
        onContextMenuClose,
    };
}
