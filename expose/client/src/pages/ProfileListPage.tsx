import {getPath, Link} from '@alchemy/navigation';
import {PublicationProfile} from '../types.ts';
import React, {useEffect, useState} from 'react';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {getProfiles} from '../api/profileApi.ts';
import {NormalizedCollectionResponse} from '@alchemy/api';
import AppBar from '../components/ui/AppBar.tsx';
import {Container, IconButton, Paper, Typography} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {routes} from '../routes.ts';
import EditIcon from '@mui/icons-material/Edit';

type Props = {};

export default function ProfileListPage({}: Props) {
    const {t} = useTranslation();
    const [data, setData] =
        useState<NormalizedCollectionResponse<PublicationProfile>>();

    useEffect(() => {
        (async () => {
            setData(await getProfiles());
        })();
    }, []);

    return (
        <Container>
            <AppBar />
            {data ? (
                <div>
                    <Typography
                        variant={'h1'}
                        sx={{
                            mb: 2,
                        }}
                    >
                        {t('profile.list.title', 'Publication Profiles')}
                    </Typography>
                    <>
                        {data.result.map(profile => (
                            <Paper
                                key={profile.id}
                                sx={{
                                    p: 2,
                                    display: 'flex',
                                }}
                            >
                                <Typography
                                    variant={'h5'}
                                    style={{
                                        flexGrow: 1,
                                    }}
                                >
                                    {profile.name}
                                </Typography>
                                <div>
                                    <IconButton
                                        component={Link}
                                        to={getPath(
                                            routes.profile.routes.edit,
                                            {
                                                id: profile.id,
                                            }
                                        )}
                                    >
                                        <EditIcon />
                                    </IconButton>
                                </div>
                            </Paper>
                        ))}
                    </>
                </div>
            ) : (
                <FullPageLoader backdrop={false} />
            )}
        </Container>
    );
}
