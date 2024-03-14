import React from 'react';
import {Button, List, ListItem, Skeleton, Stack} from "@mui/material";
import {useBasketStore} from "../../store/basketStore.ts";
import BasketMenuItem from "./BasketMenuItem.tsx";
import ConfirmDialog from "../Ui/ConfirmDialog.tsx";
import {toast} from "react-toastify";
import {useModals} from '@alchemy/navigation';
import {Basket} from "../../types.ts";
import {useTranslation} from 'react-i18next';
import CreateBasket from "./CreateBasket.tsx";
import AddIcon from "@mui/icons-material/Add";
import {useNavigateToModal} from "../Routing/ModalLink.tsx";
import {modalRoutes} from "../../routes.ts";

type Props = {};

export default function BasketsPanel({}: Props) {
    const baskets = useBasketStore(state => state.baskets);
    const loading = useBasketStore(state => state.loading);
    const load = useBasketStore(state => state.load);
    const deleteBasket = useBasketStore(state => state.deleteBasket);
    const {openModal} = useModals();
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();

    React.useEffect(() => {
        load();
    }, []);

    const onDelete = (data: Basket): void => {
        openModal(ConfirmDialog, {
            textToType: data.assetCount && data.assetCount > 1 ? (data.title || t('dialog.confirm_text_type.default', 'Confirm')) : undefined,
            title: t(
                'basket_delete.title.confirm',
                'Are you sure you want to delete this basket?'
            ),
            onConfirm: async () => {
                await deleteBasket(data.id);
                toast.success(
                    t(
                        'delete.basket.confirmed',
                        'Basket has been removed!'
                    ) as string
                );
            },
        });
    };

    const createBasket = () => {
        openModal(CreateBasket, {});
    }

    return (
        <>
            <Stack
                sx={{p: 1}}
                justifyContent={'space-between'}
            >
                <Button
                    variant={'contained'}
                    onClick={createBasket}
                    startIcon={<AddIcon/>}
                >
                    {t('basket.create_button.label', 'Create new Basket')}
                </Button>
            </Stack>
            <List
                disablePadding
                component="nav"
                aria-labelledby="nested-list-subheader"
                sx={theme => ({
                    root: {
                        width: '100%',
                        maxWidth: 360,
                        backgroundColor: theme.palette.background.paper,
                    },
                    nested: {
                        paddingLeft: theme.spacing(4),
                    },
                })}
            >
                {!loading ? baskets.map(b => <BasketMenuItem
                    key={b.id}
                    data={b}
                    onDelete={onDelete}
                    onClick={() => navigateToModal(modalRoutes.baskets.routes.view, {id: b.id})}
                />) : <>
                    <ListItem>
                        <Skeleton variant={'text'} width={'100%'}/>
                    </ListItem>
                    <ListItem>
                        <Skeleton variant={'text'} width={'100%'}/>
                    </ListItem>
                </>}
            </List>
        </>
    );
}
