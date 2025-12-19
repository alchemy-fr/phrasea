import {TextField} from '@mui/material';
import React from 'react';
import {SavedSearch} from '../../../../types.ts';
import {useTranslation} from 'react-i18next';
import {UseFormSubmitReturn} from '@alchemy/api';
import {FormFieldErrors, FormRow, SwitchWidget} from '@alchemy/react-form';
import RemoteErrors from '../../../Form/RemoteErrors.tsx';

type Props = {
    usedFormSubmit: UseFormSubmitReturn<SavedSearch>;
};

export default function SavedSearchFields({usedFormSubmit}: Props) {
    const {t} = useTranslation();

    const {
        control,
        register,
        remoteErrors,
        submitting,
        formState: {errors},
    } = usedFormSubmit;

    return (
        <>
            <FormRow>
                <TextField
                    autoFocus
                    required={true}
                    label={t('form.saved_search.title.label', 'Title')}
                    disabled={submitting}
                    {...register('title', {
                        required: true,
                    })}
                />
                <FormFieldErrors field={'title'} errors={errors} />
            </FormRow>
            <FormRow>
                <SwitchWidget
                    control={control}
                    name={'public'}
                    label={t('form.saved_search.public.label', 'Public')}
                />
            </FormRow>
            <RemoteErrors errors={remoteErrors} />
        </>
    );
}
