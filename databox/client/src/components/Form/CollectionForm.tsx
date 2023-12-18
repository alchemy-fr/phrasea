import {TextField} from '@mui/material';
import {FC} from 'react';
import {useTranslation} from 'react-i18next';
import {Collection} from '../../types';
import FormFieldErrors from './FormFieldErrors';
import PrivacyField from '../Ui/PrivacyField';
import FormRow from './FormRow';
import {FormProps} from './types';

export const CollectionForm: FC<FormProps<Collection>> = function ({
    formId,
    usedFormSubmit: {
        handleSubmit,
        submitting,
        register,
        control,
        formState: {errors},
    },
}) {
    const {t} = useTranslation();

    return (
        <form id={formId} onSubmit={handleSubmit}>
            <FormRow>
                <TextField
                    autoFocus
                    required={true}
                    label={t('form.collection.title.label', 'Title')}
                    disabled={submitting}
                    {...register('title', {
                        required: true,
                    })}
                />
                <FormFieldErrors field={'title'} errors={errors} />
            </FormRow>
            <FormRow>
                <PrivacyField control={control} name={'privacy'} />
            </FormRow>
        </form>
    );
};
