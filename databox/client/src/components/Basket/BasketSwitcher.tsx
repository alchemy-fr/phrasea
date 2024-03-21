import {useBasketStore} from "../../store/basketStore.ts";
import {Button, ButtonGroup, Chip} from "@mui/material";
import ArrowDropDownIcon from "@mui/icons-material/ArrowDropDown";
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import {useModals} from '@alchemy/navigation';
import BasketListDialog from "./BasketListDialog.tsx";
import {useTranslation} from 'react-i18next';
import {LoadingButton} from "@alchemy/react-form";
import {useNavigateToModal} from "../Routing/ModalLink.tsx";
import {modalRoutes} from "../../routes.ts";
import React, {useContext} from "react";
import {TSelectionContext} from "../../context/AssetSelectionContext.tsx";
import AddShoppingCartIcon from '@mui/icons-material/AddShoppingCart';
import {AssetOrAssetContainer} from "../../types.ts";

type Props<Item extends AssetOrAssetContainer> = {
    selectionContext: React.Context<TSelectionContext<Item>>;
};

export default function BasketSwitcher<Item extends AssetOrAssetContainer>({
    selectionContext,
}: Props<Item>) {
    const current = useBasketStore(state => state.current);
    const addToCurrent = useBasketStore(state => state.addToCurrent);
    const loadingCurrent = useBasketStore(state => state.loadingCurrent);
    const {openModal} = useModals();
    const navigateToModal = useNavigateToModal();
    const {t} = useTranslation();
    const {selection, itemToAsset, setSelection} = useContext(selectionContext);
    const hasSelection = selection.length > 0;

    const openList = () => {
        openModal(BasketListDialog, {});
    }

    const onClick = () => {
        if (hasSelection) {
            addToCurrent((itemToAsset ? selection.map(itemToAsset) : selection).map(a => ({
                id: a.id,
            })));
            setSelection([]);
        } else {
            if (current) {
                navigateToModal(modalRoutes.baskets.routes.view, {
                    id: current!.id,
                });
            } else {
                openList();
            }
        }
    }

    return <ButtonGroup
        aria-label="split button"
        disableElevation={true}
        style={{
            verticalAlign: 'middle',
        }}
    >
        <LoadingButton
            onClick={onClick}
            loading={loadingCurrent}
            loadingPosition={'start'}
            startIcon={hasSelection ? <AddShoppingCartIcon/> : <ShoppingCartIcon/>}
        >
            {current?.title || t('basket.default.title', 'Basket')}
            {current?.assetCount ? <>{' '}<Chip
                size={'small'}
                label={current!.assetCount}/></> : ''}
        </LoadingButton>
        <Button
            size="small"
            sx={{
                p: 0,
            }}
            aria-label="Select basket action"
            aria-haspopup="menu"
            onClick={() => openList()}
        >
            <ArrowDropDownIcon/>
        </Button>
    </ButtonGroup>
}
