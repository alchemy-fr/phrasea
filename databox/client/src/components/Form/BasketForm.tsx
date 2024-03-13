import {TextField} from '@mui/material';
import {FC} from 'react';
import {useTranslation} from 'react-i18next';
import {Basket} from '../../types';
import {FormFieldErrors, FormRow} from '@alchemy/react-form';
import {FormProps} from './types';

export const BasketForm: FC<FormProps<Basket>> = function ({
    formId,
    usedFormSubmit: {
        handleSubmit,
        submitting,
        register,
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
                    label={t('form.basket.title.label', 'Title')}
                    disabled={submitting}
                    {...register('title', {
                        required: true,
                    })}
                />
                <FormFieldErrors field={'title'} errors={errors}/>
            </FormRow>
        </form>
    );
};
