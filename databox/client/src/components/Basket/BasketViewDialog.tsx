import {StackedModalProps, useParams} from '@alchemy/navigation';
import {AppDialog} from '@alchemy/phrasea-ui';
import {Basket, BasketAsset} from "../../types.ts";
import {useTranslation} from 'react-i18next';
import React from "react";
import {getBasket, getBasketAssets} from "../../api/basket.ts";
import {useCloseModal} from "../Routing/ModalLink.tsx";
import AssetList from "../AssetList/AssetList.tsx";
import {BasketSelectionContext} from "../../context/BasketSelectionContext.ts";
import DisplayProvider from "../Media/DisplayProvider.tsx";
import {useBasketStore} from "../../store/basketStore.ts";
import DeleteIcon from "@mui/icons-material/Delete";

type Props = {} & StackedModalProps;

export default function BasketViewDialog({
    modalIndex,
    open,
}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();
    const closeModal = useCloseModal();

    const [pages, setPages] = React.useState<BasketAsset[][]>();
    const [total, setTotal] = React.useState<number>();
    const [data, setData] = React.useState<Basket>();

    const removeFromBasket = useBasketStore(state => state.removeFromBasket);

    const loadItems = React.useCallback(async () => {
        const r = await getBasketAssets(id!);
        setPages([r.result]);
        setTotal(r.total);
    }, [id]);

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
        title={data?.title || t('basket.default.title', 'Basket')}
        disablePadding={true}
    >
        <div style={{
            height: 'calc(100vh - 150px)',
            width: '100%',
        }}>
            <DisplayProvider>
                <AssetList
                    searchBar={false}
                    pages={pages ?? []}
                    reload={loadItems}
                    loading={!pages}
                    itemToAsset={itemToAsset}
                    selectionContext={BasketSelectionContext}
                    total={total}
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
                            apply: async (items) => {
                                await removeFromBasket(id!, items.map(i => i.id));
                            }
                        }
                    ]}
                />
            </DisplayProvider>
        </div>
    </AppDialog>
}
