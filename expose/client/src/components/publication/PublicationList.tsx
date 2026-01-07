import React, {useMemo} from 'react';
import {Button, Container, MenuItem} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {Publication, SortBy} from '../../types.ts';
import {apiClient} from '../../init.ts';
import {DropdownActions, FullPageLoader} from '@alchemy/phrasea-ui';
import SwapVertIcon from '@mui/icons-material/SwapVert';
import PublicationCard from './PublicationCard.tsx';
import Grid from '@mui/material/Unstable_Grid2'; // Grid version 2
import AppBar from '../ui/AppBar.tsx';

type Props = {};

export default function PublicationList({}: Props) {
    const [loading, setLoading] = React.useState(false);
    const [data, setData] = React.useState<Publication[] | undefined>();
    const [sortBy, setSortBy] = React.useState<SortBy>(SortBy.Date);
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

    const loadPublications = React.useCallback(async () => {
        setLoading(true);
        try {
            const res = await apiClient.get(
                `/publications?${orders[sortBy].query}`
            );
            setData(res.data['hydra:member']);
        } finally {
            setLoading(false);
        }
    }, [sortBy, orders]);

    React.useEffect(() => {
        loadPublications();
    }, [loadPublications]);

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
                        '.MuiGrid2-root': {
                            'display': 'flex',
                            '> div': {
                                display: 'flex',
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
                    {data
                        ? data.map((p: Publication) => (
                              <Grid xs={12} sm={6} md={4} key={p.id}>
                                  <PublicationCard publication={p} />
                              </Grid>
                          ))
                        : null}
                </Grid>
            </div>
        </Container>
    );
}
