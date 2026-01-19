import {Button, Container, Paper, TextField, Typography} from '@mui/material';
import AppBar from '../ui/AppBar.tsx';
import {Publication} from '../../types.ts';
import React from 'react';
import {toast} from 'react-toastify';
import {putPublication} from '../../api/publicationApi.ts';
import {useTranslation} from 'react-i18next';
import {useNavigateToPublication} from '../../hooks/useNavigateToPublication.ts';
import {normalizeNestedObjects, useFormSubmit} from '@alchemy/api';
import {FormFieldErrors, FormRow} from '@alchemy/react-form';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import PublicationSelectWidget from '../form/PublicationSelectWidget.tsx';

type Props = {
    data: Publication;
};

export default function PublicationEdit({data}: Props) {
    const {t} = useTranslation();
    const navigateToPublication = useNavigateToPublication();

    const {
        handleSubmit,
        register,
        control,
        submitting,
        forbidNavigation,
        formState: {errors},
    } = useFormSubmit<Publication>({
        defaultValues: normalizeNestedObjects(data),
        onSubmit: async data => {
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
                    {t('form.publication.edit.title', 'Edit Publication')}
                </Typography>
                <form onSubmit={handleSubmit}>
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
                            {...register('slug', {
                                required: true,
                            })}
                        />
                        <FormFieldErrors field={'slug'} errors={errors} />
                    </FormRow>

                    <FormRow>
                        <PublicationSelectWidget
                            label={t(
                                'form.publication.parent.label',
                                'Parent Publication'
                            )}
                            control={control}
                            name={'parent'}
                        />
                    </FormRow>
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
