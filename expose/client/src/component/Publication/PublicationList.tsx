import React, {useMemo} from 'react';
import {AppBar, Container, IconButton, MenuItem} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {Publication, SortBy} from '../../types.ts';
import {Logo} from '../Logo.tsx';
import {apiClient} from '../../init.ts';
import {DropdownActions, FullPageLoader} from '@alchemy/phrasea-ui';
import SwapVertIcon from '@mui/icons-material/SwapVert';
import PublicationCard from './PublicationCard.tsx';
import Grid from '@mui/material/Unstable_Grid2'; // Grid version 2
import {getPath, useNavigate} from '@alchemy/navigation';
import {routes} from '../../routes.ts';

type Props = {};

export default function PublicationList({}: Props) {
    const [loading, setLoading] = React.useState(false);
    const [data, setData] = React.useState<Publication[] | undefined>();
    const [sortBy, setSortBy] = React.useState<SortBy>(SortBy.Date);
    const {t} = useTranslation();
    const navigate = useNavigate();

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
            <AppBar>
                <h1>
                    <Logo />
                </h1>
            </AppBar>

            <DropdownActions
                mainButton={props => (
                    <IconButton {...props}>
                        <SwapVertIcon />
                    </IconButton>
                )}
            >
                {closeWrapper =>
                    Object.keys(orders).map(o => (
                        <MenuItem
                            key={o}
                            onClick={closeWrapper(() => setSortBy(o as SortBy))}
                        >
                            {orders[o as keyof typeof orders].label}
                        </MenuItem>
                    ))
                }
            </DropdownActions>

            {loading && <FullPageLoader backdrop={false} />}
            <div>
                <Grid
                    container
                    spacing={2}
                    sx={{
                        '.MuiGrid2-root': {
                            'display': 'flex',
                            '> div': {
                                width: '100%',
                            },
                        },
                    }}
                >
                    {data
                        ? data.map((p: Publication) => (
                              <Grid xs={6} md={4} lg={3} key={p.id}>
                                  <PublicationCard
                                      onClick={id =>
                                          navigate(
                                              getPath(routes.publication, {
                                                  id,
                                              })
                                          )
                                      }
                                      publication={p}
                                  />
                              </Grid>
                          ))
                        : null}
                </Grid>
            </div>
            {/*<div>*/}
            {/*    {data ? (*/}
            {/*        data.map((p: Publication) => (*/}
            {/*            <div className={'publication-item'} key={p.id}>*/}
            {/*                <Link to={`/${p.slug || p.id}`}>*/}
            {/*                    <div className="media">*/}
            {/*                        <img*/}
            {/*                            src={*/}
            {/*                                p.cover*/}
            {/*                                    ? p.cover.thumbUrl ||*/}
            {/*                                      getThumbPlaceholder(*/}
            {/*                                          p.cover.mimeType*/}
            {/*                                      )*/}
            {/*                                    : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAARMAAAC3CAMAAAAGjUrGAAAAKlBMVEXg4OD////j4+Pb29v7+/vi4uLx8fHs7Oz39/f09PTa2tru7u7m5ubX19cF3ejnAAABRElEQVR4nO3Z27JDMBiAURFVVN//dXfp+ZC6Y0//tS4zphPflARVBQAAAAAAAAAAAAAAAAAAAAAAAADAj6i/23p6G+jSkn7rKa6tXUyS0mHrSa4rp9QuHNKlJq8yl//i1GS/cEgbtsm4Kx0StEme7ipd4ZCgTQ7zrbT7fOoxm+TL+vL58ondZLwPd/2tQ+wm99HTRu4WJWaTapyTtNdTz/Pe9holaJNcnxrsh+vYZbt/iRK0SVUdj8fnf8k9StgmDyMPD4VzlNBN5qU4Pz0nT1EiN2mmdSe/vDroQzdppsX4NUlKOXCT5ry9f3t3ErhJU3qfFLdJMUncJuUkYZvkchJNNJlo8u58P6l3JXXoPVtRxCZ9PX5Tx/u+4zvgu2H5e3E7LP/MbxmWLowhXBIAAAAAAAAAAAAAAAAAAAAAAAAAgF/1BxZSCIBLTls7AAAAAElFTkSuQmCC'*/}
            {/*                            }*/}
            {/*                            className="mr-3"*/}
            {/*                            alt={p.title}*/}
            {/*                        />*/}
            {/*                        <div className="media-body">*/}
            {/*                            <h5 className="mt-0">*/}
            {/*                                {getTranslatedTitle(p)}*/}
            {/*                            </h5>*/}
            {/*                            {!p.enabled && (*/}
            {/*                                <div className="alert alert-warning mb-1">*/}
            {/*                                    This publication is currently*/}
            {/*                                    disabled. Only administrators*/}
            {/*                                    can see it.*/}
            {/*                                </div>*/}
            {/*                            )}*/}
            {/*                            {p.date ? (*/}
            {/*                                <time>*/}
            {/*                                    {moment(p.date).format('LLLL')}*/}
            {/*                                </time>*/}
            {/*                            ) : (*/}
            {/*                                ''*/}
            {/*                            )}*/}
            {/*                            <Description*/}
            {/*                                descriptionHtml={getTranslatedDescription(*/}
            {/*                                    p*/}
            {/*                                )}*/}
            {/*                            />*/}
            {/*                        </div>*/}
            {/*                    </div>*/}
            {/*                </Link>*/}
            {/*            </div>*/}
            {/*        ))*/}
            {/*    ) : (*/}
            {/*        <FullPageLoader backdrop={false} />*/}
            {/*    )}*/}
        </Container>
    );
}
