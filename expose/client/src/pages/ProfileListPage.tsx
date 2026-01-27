import {getPath, Link, useModals} from '@alchemy/navigation';
import {PublicationProfile} from '../types.ts';
import React, {useCallback, useEffect, useState} from 'react';
import {FlexRow, FullPageLoader} from '@alchemy/phrasea-ui';
import {deleteProfile, getProfiles} from '../api/profileApi.ts';
import {NormalizedCollectionResponse} from '@alchemy/api';
import AppBar from '../components/AppBar.tsx';
import {Box, Container, IconButton, Paper, Typography} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {routes} from '../routes.ts';
import EditIcon from '@mui/icons-material/Edit';
import DeleteIcon from '@mui/icons-material/Delete';
import {ConfirmDialog, NavButton} from '@alchemy/phrasea-framework';
import AddIcon from '@mui/icons-material/Add';
import LoadMoreButton from '../components/ui/LoadMoreButton.tsx';

type Props = {};

export default function ProfileListPage({}: Props) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const [loading, setLoading] = React.useState(false);
    const [data, setData] =
        useState<NormalizedCollectionResponse<PublicationProfile>>();

    const loadProfiles = useCallback(
        async (nextUrl?: string) => {
            setLoading(true);
            try {
                const res = await getProfiles({nextUrl});

                setData(p =>
                    p && nextUrl
                        ? {
                              ...res,
                              result: p.result.concat(res.result),
                          }
                        : res
                );
            } finally {
                setLoading(false);
            }
        },
        [setData]
    );

    useEffect(() => {
        loadProfiles();
    }, [loadProfiles]);

    const onDeleteProfile = React.useCallback(
        async (profile: PublicationProfile) => {
            openModal(ConfirmDialog, {
                textToType: profile.name,
                title: t('profile.delete_title', {
                    defaultValue: 'Delete Profile "{{title}}"',
                    title: profile.name,
                }),
                onConfirm: async () => {
                    await deleteProfile(profile.id);
                    loadProfiles();
                },
                confirmLabel: t('profile.delete_confirm', 'Delete Profile'),
            });
        },
        [loadProfiles, openModal]
    );

    return (
        <Container>
            <AppBar />
            {data ? (
                <div>
                    <FlexRow
                        sx={{
                            my: 2,
                        }}
                    >
                        <Typography
                            variant={'h1'}
                            sx={{
                                mb: 2,
                                flexGrow: 1,
                            }}
                        >
                            {t('profile.list.title', 'Profiles')}
                        </Typography>
                        <NavButton
                            startIcon={<AddIcon />}
                            variant={'contained'}
                            route={routes.profile.routes.create}
                        >
                            {t('profile.list.create_button', 'Create Profile')}
                        </NavButton>
                    </FlexRow>
                    <Box gap={1} display={'flex'} flexDirection={'column'}>
                        {data.result.map(profile => (
                            <Paper
                                key={profile.id}
                                sx={{
                                    p: 2,
                                    display: 'flex',
                                }}
                            >
                                <div
                                    style={{
                                        flexGrow: 1,
                                    }}
                                >
                                    <Typography variant={'h5'}>
                                        {profile.name}
                                    </Typography>
                                    {profile.publicationCount > 0 ? (
                                        <Typography
                                            variant={'body2'}
                                            color={'text.secondary'}
                                        >
                                            {t(
                                                'profile.list.using_publications',
                                                {
                                                    count: profile.publicationCount,
                                                    defaultValue:
                                                        '{{count}} publication using this profile',
                                                    defaultValue_other:
                                                        '{{count}} publications using this profile',
                                                }
                                            )}
                                        </Typography>
                                    ) : null}
                                </div>
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
                                    <IconButton
                                        color={'error'}
                                        onClick={() => onDeleteProfile(profile)}
                                        disabled={profile.publicationCount > 0}
                                    >
                                        <DeleteIcon />
                                    </IconButton>
                                </div>
                            </Paper>
                        ))}
                    </Box>
                    <LoadMoreButton
                        loading={loading}
                        data={data}
                        load={loadProfiles}
                    />
                </div>
            ) : (
                <FullPageLoader backdrop={false} />
            )}
        </Container>
    );
}
