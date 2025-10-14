import {StackedModalProps, useParams} from '@alchemy/navigation';
import {AppDialog} from '@alchemy/phrasea-ui';
import {Basket, BasketAsset} from '../../types';
import {Trans, useTranslation} from 'react-i18next';
import React from 'react';
import {getBasket, getBasketAssets} from '../../api/basket';
import {useCloseModal} from '../Routing/ModalLink';
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
import {ActionsContext} from '../AssetList/types';
import BasketItem from './BasketItem';
import {createDefaultActionsContext} from '../AssetList/actionContext.ts';
import {useOpenAsset} from '../AssetSearch/useOpenAsset.ts';

type Props = {} & StackedModalProps;

export default function BasketViewDialog({modalIndex, open}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();
    const closeModal = useCloseModal();

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

    const onOpen = useOpenAsset({});

    React.useEffect(() => {
        getBasket(id!).then(setData);
        loadItems();
    }, [loadItems, id]);

    const actionsContext = React.useMemo<ActionsContext<BasketAsset>>(() => {
        const label = t(
            'basket_view_dialog.remove_from_basket',
            `Remove from basket`
        );
        return {
            ...createDefaultActionsContext(),
            extraActions: [
                {
                    name: 'removeFromBasket',
                    labels: {
                        multi: label,
                        single: label,
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
    }, [data, removeFromBasket]);

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
                            itemLabel={selectionProps => (
                                <>
                                    {selectionProps.selectedCount > 0 ? (
                                        <Trans
                                            i18nKey={
                                                'basket_view_dialog.x_item_with_selection'
                                            }
                                            defaults={`<strong>{{selection}} / {{total}}</strong> item`}
                                            tOptions={{
                                                defaultValue_other: `<strong>{{selection}} / {{total}}</strong> items`,
                                            }}
                                            count={selectionProps.count}
                                            values={selectionProps.values}
                                        />
                                    ) : (
                                        <Trans
                                            i18nKey={
                                                'basket_view_dialog.x_item'
                                            }
                                            defaults={`<strong>{{count}}</strong> item`}
                                            tOptions={{
                                                defaultValue_other: `<strong>{{total}}</strong> items`,
                                            }}
                                            count={selectionProps.count}
                                            values={selectionProps.values}
                                        />
                                    )}
                                </>
                            )}
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
