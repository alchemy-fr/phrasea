import {useBasketStore} from "../../store/basketStore.ts";
import {Badge, BadgeProps, Button, ButtonGroup} from "@mui/material";
import ArrowDropDownIcon from "@mui/icons-material/ArrowDropDown";
import ShoppingCartIcon from '@mui/icons-material/ShoppingCart';
import {useModals} from '@alchemy/navigation';
import BasketListDialog from "./BasketListDialog.tsx";
import {useTranslation} from 'react-i18next';
import {LoadingButton} from "@alchemy/react-form";
import {styled} from "@mui/material/styles";

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
            startIcon={<StyledBadge
                badgeContent={current?.assetCount}
                color="secondary"
            >
                <ShoppingCartIcon/>
            </StyledBadge>}
        >
            {current ? current.title : t('basket.default.title', 'Basket')}
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
}

const StyledBadge = styled(Badge)<BadgeProps>(({ theme }) => ({
    '& .MuiBadge-badge': {
        marginLeft: -50,
        top: 13,
        border: `2px solid ${theme.palette.background.paper}`,
        padding: '0 4px',
    },
}));
