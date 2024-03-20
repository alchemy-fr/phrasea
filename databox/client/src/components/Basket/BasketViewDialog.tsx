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

    const loadItems = React.useCallback(async () => {
        const r = await getBasketAssets(id!);
        setPages([r.result]);
        setTotal(r.total);
    }, []);

    React.useEffect(() => {
        getBasket(id!).then(setData);
        loadItems();
    }, [loadItems]);

    return <AppDialog
        maxWidth={'xl'}
        modalIndex={modalIndex}
        open={open}
        onClose={closeModal}
        title={data?.title || t('basket.default.title', 'Basket')}
    >
        <DisplayProvider>
            <AssetList
                searchBar={false}
                pages={pages ?? []}
                reload={loadItems}
                loading={!pages}
                itemToAsset={(item: BasketAsset) => item.asset}
                selectionContext={BasketSelectionContext}
                total={total}
            />
        </DisplayProvider>
    </AppDialog>
}
