import {UseFormSubmitReturn} from '@alchemy/api';
import {
    FormFieldErrors,
    FormRow,
    RemoteErrors,
    SwitchWidget,
} from '@alchemy/react-form';
import {TextField} from '@mui/material';
import {useTranslation} from 'react-i18next';
import {Page} from '../../../../types.ts';

type Props = {
    usedFormSubmit: UseFormSubmitReturn<Page>;
};

export default function PageEditFields({usedFormSubmit}: Props) {
    const {t} = useTranslation();
    const {
        control,
        register,
        formState: {errors},
        submitting,
        remoteErrors,
    } = usedFormSubmit;

    return (
        <>
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
                    {...register('slug')}
                />
                <FormFieldErrors field={'slug'} errors={errors} />
            </FormRow>
            <FormRow>
                <SwitchWidget
                    name={'enabled'}
                    label={t('pages.form.enabled.label', 'Enabled')}
                    control={control}
                />
            </FormRow>
            <FormRow>
                <SwitchWidget
                    name={'public'}
                    label={t('pages.form.public.label', 'Public')}
                    control={control}
                />
            </FormRow>

            <RemoteErrors errors={remoteErrors} />
        </>
    );
}
