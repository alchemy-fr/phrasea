import {TextField} from '@mui/material';
import {FC} from 'react';
import {useTranslation} from 'react-i18next';
import {AttributeList} from '../../types';
import {FormFieldErrors, FormRow, SwitchWidget} from '@alchemy/react-form';
import {FormProps} from './types';

export const AttributeListForm: FC<FormProps<AttributeList>> = function ({
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
                    label={t('form.attribute_list.title.label', 'Title')}
                    disabled={submitting}
                    {...register('title', {
                        required: true,
                    })}
                />
                <FormFieldErrors field={'title'} errors={errors} />
            </FormRow>
            <FormRow>
                <TextField
                    rows={5}
                    fullWidth={true}
                    multiline={true}
                    label={t(
                        'form.attribute_list.description.label',
                        'Description'
                    )}
                    disabled={submitting}
                    {...register('description')}
                />
                <FormFieldErrors field={'description'} errors={errors} />
            </FormRow>
            <FormRow>
                <SwitchWidget
                    control={control}
                    name={'public'}
                    label={t('form.attribute_list.public.label', 'Public')}
                />
            </FormRow>
        </form>
    );
};
