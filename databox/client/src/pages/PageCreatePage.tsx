import {getPath, useNavigate} from '@alchemy/navigation';

import {Button, Container, TextField, Typography} from '@mui/material';
import {postPage} from '../api/page.ts';
import {routes} from '../routes.ts';
import {useFormSubmit} from '@alchemy/api';
import {FormFieldErrors, FormRow, RemoteErrors} from '@alchemy/react-form';
import {useTranslation} from 'react-i18next';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';

type Props = {};

export default function PageCreatePage({}: Props) {
    const navigate = useNavigate();
    const {t} = useTranslation();

    const {
        handleSubmit,
        forbidNavigation,
        formState: {errors},
        submitting,
        remoteErrors,
        register,
    } = useFormSubmit({
        defaultValues: {
            title: '',
            slug: '',
        },
        onSubmit: async data => {
            return await postPage({
                title: data.title,
                slug: data.slug,
            });
        },
        onSuccess: data => {
            navigate(
                getPath(routes.pageAdmin.routes.edit, {
                    id: data.id,
                })
            );
        },
        toastSuccess: t('pages.create.success', 'Page created successfully'),
    });

    useDirtyFormPrompt(forbidNavigation);

    return (
        <>
            <Container>
                <Typography
                    variant={'h2'}
                    sx={{
                        mb: 2,
                    }}
                >
                    {t('pages.create.title', 'Create Page')}
                </Typography>
                <form onSubmit={handleSubmit}>
                    <FormRow>
                        <TextField
                            label={t('pages.form.title.label', 'Title')}
                            disabled={submitting}
                            error={Boolean(errors.title)}
                            {...register('title', {
                                required: true,
                            })}
                        />
                        <FormFieldErrors field={'title'} errors={errors} />
                    </FormRow>
                    <FormRow>
                        <TextField
                            label={t('pages.form.slug.label', 'Slug')}
                            disabled={submitting}
                            error={Boolean(errors.slug)}
                            helperText={t(
                                'pages.form.slug.helper',
                                'The slug is used in the page URL and must be unique.'
                            )}
                            {...register('slug', {
                                required: true,
                            })}
                        />
                        <FormFieldErrors field={'slug'} errors={errors} />
                    </FormRow>
                    <FormRow>
                        <RemoteErrors errors={remoteErrors} />
                        <Button
                            type={'submit'}
                            variant={'contained'}
                            disabled={submitting}
                            loading={submitting}
                        >
                            {t('pages.create.submit', 'Create Page')}
                        </Button>
                    </FormRow>
                </form>
            </Container>
        </>
    );
}
