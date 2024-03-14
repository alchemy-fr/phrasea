import {StackedModalProps, useParams} from '@alchemy/navigation';
import {AppDialog} from '@alchemy/phrasea-ui';
import {BasketAsset} from "../../types.ts";
import {useTranslation} from 'react-i18next';
import React from "react";
import {getBasketAssets} from "../../api/basket.ts";
import {ApiCollectionResponse} from "../../api/hydra.ts";
import AssetSelection from "../Media/Asset/AssetSelection.tsx";
import {useCloseModal} from "../Routing/ModalLink.tsx";

type Props = {} & StackedModalProps;

export default function BasketViewDialog({
    modalIndex,
    open,
}: Props) {
    const {t} = useTranslation();
    const {id} = useParams();
    const closeModal = useCloseModal();

    const [assets, setAssets] = React.useState<ApiCollectionResponse<BasketAsset>>();

    React.useEffect(() => {
        getBasketAssets(id!).then(setAssets);
    }, []);

    return <AppDialog
        maxWidth={'sm'}
        modalIndex={modalIndex}
        open={open}
        onClose={closeModal}
        title={t('basket.view.title', 'Basket')}
    >
        {assets ? <AssetSelection
            assets={assets.result.map(a => a.asset)}
            onSelectionChange={() => {
            }}
        /> : ''}


    </AppDialog>
}
