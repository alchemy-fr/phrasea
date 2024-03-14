import {useBasketStore} from "../../store/basketStore.ts";
import {Button, ButtonGroup, Chip} from "@mui/material";
import ArrowDropDownIcon from "@mui/icons-material/ArrowDropDown";
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import {useModals} from '@alchemy/navigation';
import BasketListDialog from "./BasketListDialog.tsx";
import {useTranslation} from 'react-i18next';
import {LoadingButton} from "@alchemy/react-form";

type Props = {};

export default function BasketSwitcher({}: Props) {
    const current = useBasketStore(state => state.current);
    const loadingCurrent = useBasketStore(state => state.loadingCurrent);
    const {openModal} = useModals();
    const {t} = useTranslation();

    const openList = () => {
        openModal(BasketListDialog, {});
    }

    return <ButtonGroup
        aria-label="split button"
        disableElevation={true}
        style={{
            verticalAlign: 'middle',
        }}
    >
        <LoadingButton
            // onClick={onClick}
            loading={loadingCurrent}
            loadingPosition={'start'}
            startIcon={<ShoppingCartIcon/>}
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
