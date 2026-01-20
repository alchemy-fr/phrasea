import {
    Button,
    Container,
    IconButton,
    InputLabel,
    Paper,
    TextField,
    Typography,
} from '@mui/material';
import AppBar from '../ui/AppBar.tsx';
import {PublicationProfile} from '../../types.ts';
import React from 'react';
import {toast} from 'react-toastify';
import {useTranslation} from 'react-i18next';
import {normalizeNestedObjects, useFormSubmit} from '@alchemy/api';
import {FormFieldErrors, FormRow, RemoteErrors} from '@alchemy/react-form';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import PublicationConfigForm from '../form/PublicationConfigForm.tsx';
import {putProfile} from '../../api/profileApi.ts';
import {routes} from '../../routes.ts';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import {getPath, Link, useNavigate} from '@alchemy/navigation';

type Props = {
    data: PublicationProfile;
};

export default function ProfileEdit({data}: Props) {
    const {t} = useTranslation();
    const navigate = useNavigate();

    const usedFormSubmit = useFormSubmit<PublicationProfile>({
        defaultValues: {
            ...normalizeNestedObjects(data, {
                ignoredKeys: ['config'],
            }),
            config: {
                ...data.config,
                securityOptions: {
                    ...(data.config.securityOptions || {}),
                },
            },
        },
        onSubmit: async data => {
            return await putProfile(
                data.id,
                normalizeNestedObjects(data, {
                    ignoredKeys: ['config'],
                })
            );
        },
        onSuccess: () => {
            toast.success(
                t('form.profile.edit.success', 'Profile saved!') as string
            );
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
                        to={getPath(routes.profile.routes.index, {id: data.id})}
                        sx={{
                            mr: 1,
                        }}
                    >
                        <ArrowBackIcon />
                    </IconButton>
                    {t('form.profile.edit.title', 'Edit Profile')}
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
