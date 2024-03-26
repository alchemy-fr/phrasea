import {StackedModalProps, useParams} from '@alchemy/navigation';
import {AppDialog} from '@alchemy/phrasea-ui';
import {Basket, BasketAsset} from "../../types.ts";
import {useTranslation} from 'react-i18next';
import React, {useCallback} from "react";
import {getBasket, getBasketAssets} from "../../api/basket.ts";
import {useCloseModal, useNavigateToModal} from "../Routing/ModalLink.tsx";
import AssetList from "../AssetList/AssetList.tsx";
import {BasketSelectionContext} from "../../context/BasketSelectionContext.ts";
import DisplayProvider from "../Media/DisplayProvider.tsx";
import {useBasketStore} from "../../store/basketStore.ts";
import DeleteIcon from "@mui/icons-material/Delete";
import {createDefaultPagination, createLoadMore, createPaginatedLoader, Pagination} from "../../api/pagination.ts";
import {Button} from "@mui/material";
import BasketsPanel from "./BasketsPanel.tsx";
import {leftPanelWidth} from "../../themes/base.ts";
import {zIndex} from "../../themes/zIndex.ts";
import Box from "@mui/material/Box";
import {OnOpen} from "../AssetList/types.ts";
import {modalRoutes} from "../../routes.ts";
import BasketItem from "./BasketItem.tsx";

type Props = {} & StackedModalProps;

export default function BasketViewDialog({
    modalIndex,
    open,
}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();
    const closeModal = useCloseModal();
    const navigateToModal = useNavigateToModal();

    const [data, setData] = React.useState<Basket>();
    const [pagination, setPagination] = React.useState<Pagination<BasketAsset>>(createDefaultPagination<BasketAsset>());

    const removeFromBasket = useBasketStore(state => state.removeFromBasket);

    const loadItems = React.useCallback(createPaginatedLoader((next) => getBasketAssets(id!, next), setPagination), [id]);
    const loadMore = React.useMemo(() => createLoadMore(loadItems, pagination), [
        loadItems, pagination,
    ]);

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



    const itemToAsset = (item: BasketAsset) => item.asset;

    return <AppDialog
        maxWidth={'xl'}
        modalIndex={modalIndex}
        open={open}
        onClose={closeModal}
        disablePadding={true}
        actions={({onClose}) => <>
            <Button
                onClick={onClose}
            >
                {t('dialog.close', 'Close')}
            </Button>
        </>}
    >
        <DisplayProvider>
            <div style={{
                display: 'flex',
                alignItems: 'stretch'
            }}>
                <Box sx={theme => ({
                    width: leftPanelWidth,
                    overflow: 'auto',
                    boxShadow: theme.shadows[5],
                    zIndex: zIndex.leftPanel,
                })}>
                    <BasketsPanel
                        selected={id!}
                    />
                </Box>
                <div style={{
                    height: 'calc(100vh - 120px)',
                    width: '100%',
                }}>
                    <AssetList
                        searchBar={false}
                        itemComponent={BasketItem}
                        pages={pagination.pages}
                        reload={loadItems}
                        loading={pagination.loading}
                        itemToAsset={itemToAsset}
                        loadMore={loadMore}
                        itemLabel={'item'}
                        selectionContext={BasketSelectionContext}
                        total={pagination.total}
                        onOpen={onOpen}
                        actions={[
                            {
                                name: 'removeFromBasket',
                                labels: {
                                    multi: 'Remove from basket',
                                    single: 'Remove from basket',
                                },
                                buttonProps: {
                                    color: 'warning',
                                    variant: 'contained',
                                    startIcon: <DeleteIcon/>
                                },
                                reload: true,
                                resetSelection: true,
                                disabled: !data?.capabilities.canEdit,
                                apply: async (items) => {
                                    await removeFromBasket(id!, items.map(i => i.id));
                                }
                            }
                        ]}
                    />
                </div>
            </div>
        </DisplayProvider>
    </AppDialog>
}
