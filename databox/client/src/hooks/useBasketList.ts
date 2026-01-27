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
    const {openModal} = useModals();
    const navigateToModal = useNavigateToModal();

    const onDelete = (data: Basket): void => {
        onContextMenuClose();
        openModal(ConfirmDialog, {
            textToType: data.title,
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

    const {
        searchQuery,
        setSearchQuery,
        results,
        searchResult,
        loadMoreHandler,
        hasMore: hasLoadMore,
        searchHandler,
    } = useSearch({
        items: baskets,
        loadItems: load,
        hasMore: hasMore(),
        loadMore: loadMore,
        search: (q, nextUrl) => getBaskets(nextUrl, {query: q}),
    });

    return {
        onEdit,
        onDelete,
        searchQuery,
        setSearchQuery,
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
