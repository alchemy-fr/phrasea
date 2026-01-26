import {
    Button,
    Container,
    IconButton,
    InputLabel,
    Paper,
    TextField,
    Typography,
} from '@mui/material';
import AppBar from '../AppBar.tsx';
import {PublicationProfile} from '../../types.ts';
import React from 'react';
import {toast} from 'react-toastify';
import {useTranslation} from 'react-i18next';
import {normalizeNestedObjects, useFormSubmit} from '@alchemy/api';
import {FormFieldErrors, FormRow, RemoteErrors} from '@alchemy/react-form';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import PublicationConfigForm from '../form/PublicationConfigForm.tsx';
import {postProfile, putProfile} from '../../api/profileApi.ts';
import {routes} from '../../routes.ts';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import {getPath, Link, useNavigate} from '@alchemy/navigation';

type Props = {
    data?: PublicationProfile;
};

export default function ProfileEdit({data: profile}: Props) {
    const {t} = useTranslation();
    const navigate = useNavigate();

    const usedFormSubmit = useFormSubmit<PublicationProfile>({
        defaultValues: profile
            ? {
                  ...normalizeNestedObjects(profile, {
                      ignoredKeys: ['config'],
                  }),
                  config: {
                      ...profile.config,
                      securityOptions: {
                          ...(profile.config.securityOptions || {}),
                      },
                  },
              }
            : {
                  name: '',
                  config: {
                      securityOptions: {},
                  },
              },
        onSubmit: async data => {
            if (!profile) {
                return await postProfile(
                    normalizeNestedObjects(data, {
                        ignoredKeys: ['config'],
                    })
                );
            } else {
                return await putProfile(
                    data.id,
                    normalizeNestedObjects(data, {
                        ignoredKeys: ['config'],
                    })
                );
            }
        },
        onSuccess: () => {
            if (profile) {
                toast.success(
                    t('form.profile.edit.success', 'Profile saved!') as string
                );
            } else {
                toast.success(
                    t(
                        'form.profile.create.success',
                        'Profile created!'
                    ) as string
                );
            }
            navigate(getPath(routes.profile.routes.index));
        },
    });

    const {
        handleSubmit,
        register,
        submitting,
        remoteErrors,
        forbidNavigation,
        formState: {errors},
    } = usedFormSubmit;

    useDirtyFormPrompt(forbidNavigation);

    return (
        <Container>
            <AppBar />
            <Paper
                sx={{
                    p: 3,
                }}
            >
                <Typography
                    variant={'h1'}
                    sx={{
                        mb: 3,
                    }}
                >
                    <IconButton
                        component={Link}
                        to={getPath(routes.profile.routes.index)}
                        sx={{
                            mr: 1,
                        }}
                    >
                        <ArrowBackIcon />
                    </IconButton>
                    {profile
                        ? t('form.profile.edit.title', 'Edit Profile')
                        : t('form.profile.create.title', 'Create Profile')}
                </Typography>
                <form onSubmit={handleSubmit}>
                    <FormRow>
                        <TextField
                            label={t('form.profile.name.label', 'Name')}
                            disabled={submitting}
                            {...register('name', {
                                required: true,
                            })}
                        />
                        <FormFieldErrors field={'name'} errors={errors} />
                    </FormRow>

                    <FormRow>
                        <InputLabel>
                            {t('form.profile.config.label', 'Configuration')}
                        </InputLabel>
                        <PublicationConfigForm
                            path={'config'}
                            usedFormSubmit={usedFormSubmit}
                        />
                    </FormRow>

                    <RemoteErrors errors={remoteErrors} />

                    <FormRow>
                        <Button
                            loading={submitting}
                            variant={'contained'}
                            type={'submit'}
                        >
                            {t('form.profile.edit.submit', 'Save Profile')}
                        </Button>
                    </FormRow>
                </form>
            </Paper>
        </Container>
    );
}
