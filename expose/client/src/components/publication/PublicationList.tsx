import React, {useMemo} from 'react';
import {Button, Container, Grid2 as Grid, MenuItem} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {Publication, SortBy} from '../../types.ts';
import {apiClient} from '../../init.ts';
import {DropdownActions, FullPageLoader} from '@alchemy/phrasea-ui';
import SwapVertIcon from '@mui/icons-material/SwapVert';
import PublicationCard from './PublicationCard.tsx';
import AppBar from '../AppBar.tsx';
import {ConfirmDialog, wrapCached} from '@alchemy/phrasea-framework';
import {deletePublication} from '../../api/publicationApi.ts';
import {useModals} from '@alchemy/navigation';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';
import KeyboardArrowDownIcon from '@mui/icons-material/KeyboardArrowDown';

type Props = {};

export default function PublicationList({}: Props) {
    const [loading, setLoading] = React.useState(false);
    const [data, setData] = React.useState<
        NormalizedCollectionResponse<Publication> | undefined
    >();
    const [sortBy, setSortBy] = React.useState<SortBy>(SortBy.Date);
    const {openModal} = useModals();
    const {t} = useTranslation();

    const orders = useMemo(
        () => ({
            [SortBy.Date]: {
                label: t('order.last_post_added', 'Recents'),
                query: `order[createdAt]=desc`,
            },
            [SortBy.Name]: {
                label: t('order.publication_name', 'Name'),
                query: `order[title]=asc`,
            },
        }),
        [t]
    );

    const loadPublications = React.useCallback(
        async (nextUrl?: string) => {
            const res = await wrapCached(
                nextUrl ?? `publications_${sortBy}`,
                5 * 60 * 1000,
                async () => {
                    setLoading(true);
                    try {
                        const res = await apiClient.get(
                            nextUrl ?? `/publications?${orders[sortBy].query}`
                        );
                        return getHydraCollection<Publication>(res.data);
                    } finally {
                        setLoading(false);
                    }
                }
            );

            setData(p =>
                p && nextUrl
                    ? {
                          ...res,
                          result: p.result.concat(res.result),
                      }
                    : res
            );
        },
        [sortBy, orders]
    );

    React.useEffect(() => {
        loadPublications();
    }, [loadPublications]);

    const onDeletePublication = React.useCallback(
        async (publication: Publication) => {
            openModal(ConfirmDialog, {
                textToType: publication.title,
                title: t('publication.delete_title', {
                    defaultValue: 'Delete publication "{{title}}"',
                    title: publication.title,
                }),
                onConfirm: async () => {
                    await deletePublication(publication.id);
                    loadPublications();
                },
                confirmLabel: t(
                    'publication.delete_confirm',
                    'Delete publication'
                ),
            });
        },
        [loadPublications, openModal]
    );

    return (
        <Container>
            <AppBar />
            <div
                style={{
                    display: 'flex',
                    justifyContent: 'flex-end',
                    marginBottom: '16px',
                }}
            >
                <DropdownActions
                    anchorOrigin={{
                        vertical: 'bottom',
                        horizontal: 'right',
                    }}
                    transformOrigin={{
                        vertical: 'top',
                        horizontal: 'right',
                    }}
                    mainButton={props => (
                        <Button startIcon={<SwapVertIcon />} {...props}>
                            {t('publication.sort_by', {
                                defaultValue: 'Sort by {{order}}',
                                order: orders[sortBy as keyof typeof orders]
                                    .label,
                            })}
                        </Button>
                    )}
                >
                    {closeWrapper =>
                        Object.keys(orders).map(o => (
                            <MenuItem
                                key={o}
                                onClick={closeWrapper(() =>
                                    setSortBy(o as SortBy)
                                )}
                                selected={o === sortBy}
                            >
                                {orders[o as keyof typeof orders].label}
                            </MenuItem>
                        ))
                    }
                </DropdownActions>
            </div>

            {loading && <FullPageLoader backdrop={false} />}
            <div>
                <Grid
                    container
                    spacing={2}
                    sx={{
                        'mb': 5,
                        '.MuiGrid2-root': {
                            'display': 'flex',
                            '> div': {
                                display: 'flex',
                                flexDirection: 'column',
                                width: '100%',
                                flexGrow: 1,
                            },
                            '.MuiCardActionArea-root': {
                                display: 'flex',
                                flexDirection: 'column',
                                height: '100%',
                                justifyContent: 'space-between',
                            },
                            '.MuiCardContent-root': {
                                flexGrow: 1,
                            },
                        },
                    }}
                >
                    {data ? (
                        <>
                            {data.result.map((p: Publication) => (
                                <Grid
                                    size={{
                                        xs: 12,
                                        sm: 6,
                                        md: 4,
                                    }}
                                    key={p.id}
                                >
                                    <PublicationCard
                                        onDelete={onDeletePublication}
                                        publication={p}
                                    />
                                </Grid>
                            ))}
                            {data?.next ? (
                                <div
                                    style={{
                                        display: 'flex',
                                        justifyContent: 'center',
                                        width: '100%',
                                    }}
                                >
                                    <Button
                                        variant={'outlined'}
                                        loading={loading}
                                        disabled={loading}
                                        onClick={() => {
                                            loadPublications(data.next!);
                                        }}
                                        startIcon={<KeyboardArrowDownIcon />}
                                    >
                                        {t(
                                            'publication.load_more',
                                            'Load more'
                                        )}
                                    </Button>
                                </div>
                            ) : null}
                        </>
                    ) : null}
                </Grid>
            </div>
        </Container>
    );
}
