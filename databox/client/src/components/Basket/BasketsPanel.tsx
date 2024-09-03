import React from 'react';
import {
    Button,
    List,
    ListItem,
    Skeleton,
    Stack,
    TextField,
} from '@mui/material';
import {useBasketStore} from '../../store/basketStore';
import BasketMenuItem from './BasketMenuItem';
import ConfirmDialog from '../Ui/ConfirmDialog';
import {toast} from 'react-toastify';
import {useModals} from '@alchemy/navigation';
import {Basket} from '../../types';
import {useTranslation} from 'react-i18next';
import CreateBasket from './CreateBasket';
import AddIcon from '@mui/icons-material/Add';
import {useNavigateToModal} from '../Routing/ModalLink';
import {modalRoutes} from '../../routes';
import {LoadingButton} from '@mui/lab';
import {getBaskets} from '../../api/basket.ts';
import {
    createDefaultPagination,
    createPaginatedLoader,
    Pagination,
} from '../../api/pagination.ts';

type Props = {
    selected?: string;
};

function BasketsPanel({selected}: Props) {
    const {t} = useTranslation();
    const baskets = useBasketStore(state => state.baskets);
    const loading = useBasketStore(state => state.loading);
    const loadMore = useBasketStore(state => state.loadMore);
    const hasMore = useBasketStore(state => state.hasMore);
    const load = useBasketStore(state => state.load);
    const deleteBasket = useBasketStore(state => state.deleteBasket);
    const {openModal} = useModals();
    const navigateToModal = useNavigateToModal();

    const [searchQuery, setSearchQuery] = React.useState<string>('');
    const [searchResult, setSearchResult] = React.useState<Pagination<Basket>>({
        ...createDefaultPagination(),
        loading: false,
    });
    const [loadedSearchQuery, setLoadedSearchQuery] = React.useState<
        string | undefined
    >();

    React.useEffect(() => {
        load();
    }, []);

    const loadItems = React.useCallback(
        createPaginatedLoader(async next => {
            const r = await getBaskets(next, {
                query: searchQuery,
            });
            setLoadedSearchQuery(searchQuery);

            return r;
        }, setSearchResult),
        [searchQuery]
    );

    React.useEffect(() => {
        if (!searchQuery) {
            setLoadedSearchQuery(undefined);
        }
    }, [searchQuery]);

    const onDelete = (data: Basket): void => {
        openModal(ConfirmDialog, {
            textToType: data.title,
            title: t(
                'basket_delete.confirm',
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
    };

    const loadMoreHandler = () =>
        loadedSearchQuery
            ? loadItems(searchResult.next || undefined)
            : loadMore();
    const hasLOadMore = loadedSearchQuery ? !!searchResult.next : hasMore();

    const results = loadedSearchQuery ? searchResult?.pages.flat() : baskets;

    return (
        <div
            style={{
                position: 'relative',
                flexGrow: 1,
            }}
        >
            <Stack sx={{p: 1}} direction={'row'}>
                <form
                    onSubmit={e => {
                        e.preventDefault();
                        loadItems();
                    }}
                >
                    <TextField
                        value={searchQuery}
                        onChange={e => setSearchQuery(e.target.value)}
                        size={'small'}
                        type={'search'}
                        placeholder={t('common.search.placeholder', 'Search…')}
                    />
                    <LoadingButton
                        variant={'contained'}
                        disabled={!searchQuery}
                        loading={searchResult.loading}
                        type={'submit'}
                    >
                        {t('common.search.submit', 'Search')}
                    </LoadingButton>
                </form>
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
                {!loading ? (
                    results.map(b => (
                        <BasketMenuItem
                            key={b.id}
                            data={b}
                            selected={selected === b.id}
                            onDelete={onDelete}
                            onClick={() =>
                                navigateToModal(
                                    modalRoutes.baskets.routes.view,
                                    {id: b.id}
                                )
                            }
                        />
                    ))
                ) : (
                    <>
                        <ListItem>
                            <Skeleton variant={'text'} width={'100%'} />
                        </ListItem>
                        <ListItem>
                            <Skeleton variant={'text'} width={'100%'} />
                        </ListItem>
                    </>
                )}
            </List>
            {hasLOadMore ? (
                <Stack
                    sx={{
                        p: 1,
                    }}
                >
                    <Button variant={'contained'} onClick={loadMoreHandler}>
                        {t('load_more.button.loading', 'Load more')}
                    </Button>
                </Stack>
            ) : (
                ''
            )}

            <Stack
                sx={{
                    p: 1,
                    position: 'sticky',
                    bottom: 0,
                }}
            >
                <Button
                    variant={'contained'}
                    onClick={createBasket}
                    startIcon={<AddIcon />}
                >
                    {t('basket.create_button.label', 'New Basket')}
                </Button>
            </Stack>
        </div>
    );
}

export default React.memo(BasketsPanel);
