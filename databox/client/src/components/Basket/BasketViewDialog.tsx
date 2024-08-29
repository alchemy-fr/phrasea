import {StackedModalProps, useParams} from '@alchemy/navigation';
import {AppDialog} from '@alchemy/phrasea-ui';
import {Basket, BasketAsset} from '../../types';
import {useTranslation} from 'react-i18next';
import React, {useCallback} from 'react';
import {getBasket, getBasketAssets} from '../../api/basket';
import {useCloseModal, useNavigateToModal} from '../Routing/ModalLink';
import AssetList from '../AssetList/AssetList';
import {BasketSelectionContext} from '../../context/BasketSelectionContext';
import DisplayProvider from '../Media/DisplayProvider';
import {useBasketStore} from '../../store/basketStore';
import DeleteIcon from '@mui/icons-material/Delete';
import {
    createDefaultPagination,
    createLoadMore,
    createPaginatedLoader,
    Pagination,
} from '../../api/pagination';
import {Button} from '@mui/material';
import BasketsPanel from './BasketsPanel';
import {leftPanelWidth} from '../../themes/base';
import {ZIndex} from '../../themes/zIndex';
import Box from '@mui/material/Box';
import {ActionsContext, OnOpen} from '../AssetList/types';
import {modalRoutes} from '../../routes';
import BasketItem from './BasketItem';
import {createDefaultActionsContext} from '../AssetList/actionContext.ts';

type Props = {} & StackedModalProps;

export default function BasketViewDialog({modalIndex, open}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();
    const closeModal = useCloseModal();
    const navigateToModal = useNavigateToModal();

    const [data, setData] = React.useState<Basket>();
    const [pagination, setPagination] = React.useState<Pagination<BasketAsset>>(
        createDefaultPagination<BasketAsset>()
    );

    const removeFromBasket = useBasketStore(state => state.removeFromBasket);

    const loadItems = React.useCallback(
        createPaginatedLoader(
            next => getBasketAssets(id!, next),
            setPagination
        ),
        [id]
    );
    const loadMore = React.useMemo(
        () => createLoadMore(loadItems, pagination),
        [loadItems, pagination]
    );

    const onOpen = useCallback<OnOpen>(
        (asset, renditionId): void => {
            navigateToModal(modalRoutes.assets.routes.view, {
                id: asset.id,
                renditionId,
            });
            // eslint-disable-next-line
        },
        [navigateToModal]
    );

    React.useEffect(() => {
        getBasket(id!).then(setData);
        loadItems();
    }, [loadItems, id]);

    const actionsContext = React.useMemo<ActionsContext<BasketAsset>>(() => {
        return {
            ...createDefaultActionsContext(),
            extraActions: [
                {
                    name: 'removeFromBasket',
                    labels: {
                        multi: 'Remove from basket',
                        single: 'Remove from basket',
                    },
                    color: 'warning',
                    icon: <DeleteIcon />,
                    buttonProps: {
                        variant: 'contained',
                    },
                    reload: true,
                    resetSelection: true,
                    disabled: !data?.capabilities.canEdit,
                    apply: async items => {
                        await removeFromBasket(
                            id!,
                            items.map(i => i.id)
                        );
                    },
                },
            ],
        };
    }, [removeFromBasket]);

    const itemToAsset = (item: BasketAsset) => item.asset;

    return (
        <AppDialog
            maxWidth={'xl'}
            modalIndex={modalIndex}
            open={open}
            onClose={closeModal}
            disablePadding={true}
            actions={({onClose}) => (
                <>
                    <Button onClick={onClose}>
                        {t('dialog.close', 'Close')}
                    </Button>
                </>
            )}
        >
            <DisplayProvider>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'stretch',
                    }}
                >
                    <Box
                        sx={theme => ({
                            width: leftPanelWidth,
                            overflow: 'auto',
                            boxShadow: theme.shadows[5],
                            zIndex: ZIndex.leftPanel,
                        })}
                    >
                        <BasketsPanel selected={id!} />
                    </Box>
                    <div
                        style={{
                            height: 'calc(100vh - 120px)',
                            width: '100%',
                        }}
                    >
                        <AssetList
                            searchBar={false}
                            itemComponent={BasketItem}
                            pages={pagination.pages}
                            reload={loadItems}
                            loading={pagination.loading}
                            itemToAsset={itemToAsset}
                            loadMore={loadMore}
                            itemLabel={t('basket_view_dialog.item', `item`)}
                            selectionContext={BasketSelectionContext}
                            total={pagination.total}
                            onOpen={onOpen}
                            previewZIndex={ZIndex.modal + 1}
                            actionsContext={actionsContext}
                        />
                    </div>
                </div>
            </DisplayProvider>
        </AppDialog>
    );
}
