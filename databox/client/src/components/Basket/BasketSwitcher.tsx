import {useBasketStore} from '../../store/basketStore';
import {Button, ButtonGroup, Chip} from '@mui/material';
import ArrowDropDownIcon from '@mui/icons-material/ArrowDropDown';
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import {useModals} from '@alchemy/navigation';
import BasketListDialog from './BasketListDialog';
import {useTranslation} from 'react-i18next';
import {LoadingButton} from '@alchemy/react-form';
import {useNavigateToModal} from '../Routing/ModalLink';
import {modalRoutes} from '../../routes';
import React from 'react';
import AddShoppingCartIcon from '@mui/icons-material/AddShoppingCart';
import {AssetOrAssetContainer} from '../../types';
import {ParentSelectionContext} from '../AssetList/Toolbar/SelectionActions.tsx';

type Props<Item extends AssetOrAssetContainer> = {
    selectionContext: ParentSelectionContext<Item>;
};

export default function BasketSwitcher<Item extends AssetOrAssetContainer>({
    selectionContext,
}: Props<Item>) {
    const {t} = useTranslation();
    const current = useBasketStore(state => state.current);
    const addToCurrent = useBasketStore(state => state.addToCurrent);
    const loadingCurrent = useBasketStore(state => state.loadingCurrent);
    const {openModal} = useModals();
    const navigateToModal = useNavigateToModal();
    const {selection, itemToAsset, setSelection} = selectionContext;
    const hasSelection = selection.length > 0;

    const openList = () => {
        openModal(BasketListDialog, {});
    };

    const onClick = () => {
        if (hasSelection) {
            addToCurrent(
                (itemToAsset ? selection.map(itemToAsset) : selection).map(
                    a => ({
                        id: a.id,
                    })
                )
            );
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
    };

    return (
        <ButtonGroup
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
                startIcon={
                    hasSelection ? (
                        <AddShoppingCartIcon />
                    ) : (
                        <ShoppingCartIcon />
                    )
                }
            >
                {current?.title || t('basket.default.title', 'My Basket')}
                {current?.assetCount ? (
                    <>
                        {' '}
                        <Chip size={'small'} label={current!.assetCount} />
                    </>
                ) : (
                    ''
                )}
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
                <ArrowDropDownIcon />
            </Button>
        </ButtonGroup>
    );
}
