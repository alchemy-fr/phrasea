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
import {Publication} from '../../types.ts';
import React from 'react';
import {toast} from 'react-toastify';
import {putPublication} from '../../api/publicationApi.ts';
import {useTranslation} from 'react-i18next';
import {useNavigateToPublication} from '../../hooks/useNavigateToPublication.ts';
import {
    extractIdFromIri,
    normalizeNestedObjects,
    useFormSubmit,
} from '@alchemy/api';
import {
    DateWidget,
    FormFieldErrors,
    FormRow,
    RemoteErrors,
} from '@alchemy/react-form';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import PublicationSelectWidget from '../form/PublicationSelectWidget.tsx';
import PublicationConfigForm from '../form/PublicationConfigForm.tsx';
import ProfileSelectWidget from '../form/ProfileSelectWidget.tsx';
import {getProfile} from '../../api/profileApi.ts';
import {getPath, Link} from '@alchemy/navigation';
import {routes} from '../../routes.ts';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import EditIcon from '@mui/icons-material/Edit';
import {FormConst} from './types.ts';

type Props = {
    data: Publication;
};

export default function PublicationEdit({data}: Props) {
    const {t} = useTranslation();
    const navigateToPublication = useNavigateToPublication();

    const usedFormSubmit = useFormSubmit<Publication>({
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
            // @ts-expect-error Unknown property
            delete data[FormConst.FallbackProfileProps];

            return await putPublication(data.id, data);
        },
        onSuccess: data => {
            toast.success(
                t(
                    'form.publication.edit.success',
                    'Publication saved!'
                ) as string
            );
            navigateToPublication(data);
        },
    });

    const {
        handleSubmit,
        register,
        control,
        submitting,
        remoteErrors,
        forbidNavigation,
        formState: {errors},
        watch,
        setValue,
    } = usedFormSubmit;

    useDirtyFormPrompt(forbidNavigation);

    const profileId = extractIdFromIri(watch('profile') as string | undefined);

    React.useEffect(() => {
        if (profileId) {
            (async () => {
                setValue(
                    FormConst.FallbackProfileProps as any,
                    await getProfile(extractIdFromIri(profileId))
                );
            })();
        } else {
            setValue(FormConst.FallbackProfileProps as any, null);
        }
    }, [profileId, setValue]);

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
                        to={getPath(routes.publicationView, {id: data.id})}
                        sx={{
                            mr: 1,
                        }}
                    >
                        <ArrowBackIcon />
                    </IconButton>
                    {t('form.publication.edit.title', 'Edit Publication')}
                </Typography>
                <form onSubmit={handleSubmit}>
                    <FormRow>
                        <PublicationSelectWidget
                            label={t(
                                'form.publication.parent.label',
                                'Parent Publication'
                            )}
                            control={control}
                            name={'parent'}
                        />
                        <FormFieldErrors field={'parent'} errors={errors} />
                    </FormRow>
                    <FormRow>
                        <TextField
                            label={t('form.publication.title.label', 'Title')}
                            disabled={submitting}
                            {...register('title', {
                                required: true,
                            })}
                        />
                        <FormFieldErrors field={'title'} errors={errors} />
                    </FormRow>
                    <FormRow>
                        <TextField
                            label={t('form.publication.slug.label', 'Slug')}
                            helperText={t(
                                'form.publication.slug.helper',
                                'The slug is used in the publication URL.'
                            )}
                            disabled={submitting}
                            {...register('slug')}
                        />
                        <FormFieldErrors field={'slug'} errors={errors} />
                    </FormRow>

                    <FormRow>
                        <DateWidget
                            control={control}
                            label={t('form.publication.date.label', 'Date')}
                            name={`date` as any}
                            disabled={submitting}
                        />
                        <FormFieldErrors field={'date'} errors={errors} />
                    </FormRow>

                    <FormRow>
                        <ProfileSelectWidget
                            label={t(
                                'form.publication.profile.label',
                                'Profile'
                            )}
                            control={control}
                            name={'profile'}
                        />

                        {profileId ? (
                            <Button
                                sx={{
                                    mt: 1,
                                }}
                                startIcon={<EditIcon />}
                                variant={'outlined'}
                                component={Link}
                                to={getPath(routes.profile.routes.edit, {
                                    id: profileId,
                                })}
                            >
                                {t(
                                    'form.publication.profile.edit',
                                    'Edit Profile'
                                )}
                            </Button>
                        ) : null}

                        <FormFieldErrors field={'profile'} errors={errors} />
                    </FormRow>

                    <FormRow>
                        <InputLabel>
                            {t(
                                'form.publication.config.label',
                                'Configuration'
                            )}
                        </InputLabel>
                        <PublicationConfigForm
                            path={'config'}
                            profileId={profileId}
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
                            {t(
                                'form.publication.edit.submit',
                                'Save Publication'
                            )}
                        </Button>
                    </FormRow>
                </form>
            </Paper>
        </Container>
    );
}
