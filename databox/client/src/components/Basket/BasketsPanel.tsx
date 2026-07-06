import React, {useState} from 'react';
import {
    Button,
    FormControlLabel,
    List,
    MenuItem,
    Stack,
    Switch,
} from '@mui/material';
import BasketMenuItem from './BasketMenuItem';
import {useTranslation} from 'react-i18next';
import AddIcon from '@mui/icons-material/Add';
import {modalRoutes} from '../../routes';
import SearchBar from '../Ui/SearchBar.tsx';
import {useBasketList} from '../../hooks/useBasketList.ts';
import {useNavigateToModal} from '../Routing/ModalLink.tsx';
import BasketSkeleton from './BasketSkeleton.tsx';
import BasketContextMenu from './BasketContextMenu.tsx';
import {LoadMoreRow, MoreActionsButton} from '@alchemy/phrasea-ui';
import {useBasketStore} from '../../store/basketStore.ts';

type Props = {
    selected?: string;
};

function BasketsPanel({selected}: Props) {
    const {t} = useTranslation();
    const navigateToModal = useNavigateToModal();
    const load = useBasketStore(state => state.load);

    const {
        contextMenu,
        onContextMenuOpen,
        onContextMenuClose,
        onEdit,
        onArchive,
        onUnarchive,
        onDelete,
        createBasket,
        loading,
        searchQuery,
        setSearchQuery,
        searchQueryOptions,
        setSearchQueryOptions,
        baskets,
        searchResult,
        loadMoreHandler,
        hasLoadMore,
        searchHandler,
    } = useBasketList();

    const [displayArchive, setDisplayArchive] = useState(false);

    const setDisplayArchiveHandler = (value: boolean) => {
        setDisplayArchive(value);
        setSearchQueryOptions({displayArchived: value});
    };

    React.useEffect(() => {
        if (Object.keys(searchQueryOptions).length > 0) {
            load({displayArchived: displayArchive});
        }
    }, [displayArchive]);

    return (
        <div
            style={{
                position: 'relative',
                flexGrow: 1,
            }}
        >
            <SearchBar
                name={'basket-search'}
                searchQuery={searchQuery}
                setSearchQuery={setSearchQuery}
                loading={searchResult.loading}
                searchHandler={searchHandler}
                settings={
                    <MoreActionsButton
                        vertical={true}
                        iconButtonProps={{
                            size: 'small',
                        }}
                    >
                        {closeWrapper => [
                            <MenuItem
                                key={'display_archive'}
                                onClick={closeWrapper()}
                            >
                                <FormControlLabel
                                    control={
                                        <Switch
                                            checked={displayArchive}
                                            onChange={() =>
                                                setDisplayArchiveHandler(
                                                    !displayArchive
                                                )
                                            }
                                        />
                                    }
                                    label={t(
                                        'basket.display_archive.label',
                                        'Display Archive'
                                    )}
                                />
                            </MenuItem>,
                        ]}
                    </MoreActionsButton>
                }
            />
            {contextMenu ? (
                <BasketContextMenu
                    contextMenu={contextMenu}
                    onContextMenuClose={onContextMenuClose}
                    onEdit={onEdit}
                    onArchive={onArchive}
                    onDelete={onDelete}
                    onUnarchive={onUnarchive}
                    onContextMenuOpen={onContextMenuOpen}
                />
            ) : null}
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
                    baskets.map(b =>
                        b.isArchived && !displayArchive ? (
                            ''
                        ) : (
                            <BasketMenuItem
                                onContextMenu={e =>
                                    onContextMenuOpen(e, b, e.currentTarget)
                                }
                                key={b.id}
                                data={b}
                                selected={selected === b.id}
                                onClick={() =>
                                    navigateToModal(
                                        modalRoutes.baskets.routes.view,
                                        {id: b.id}
                                    )
                                }
                            />
                        )
                    )
                ) : (
                    <BasketSkeleton />
                )}
            </List>
            <LoadMoreRow
                hasMore={hasLoadMore}
                loading={loading}
                onClick={loadMoreHandler}
            />

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
                    {t('basket.create_button.label', 'Create new Basket')}
                </Button>
            </Stack>
        </div>
    );
}

export default React.memo(BasketsPanel);
