import {TextField} from '@mui/material';
import React, {FC} from 'react';
import {useTranslation} from 'react-i18next';
import {Collection} from '../../types';
import {FormFieldErrors, TranslatedField} from '@alchemy/react-form';
import PrivacyField from '../Ui/PrivacyField';
import {FormRow} from '@alchemy/react-form';
import {FormProps} from './types';
import {useCreateSaveTranslations} from '../../hooks/useCreateSaveTranslations.ts';
import {putCollection} from '../../api/collection.ts';
import {getLocaleOptions} from '../../api/locale.ts';
import {useWorkspace} from '../../hooks/useWorkspace.ts';

export const CollectionForm: FC<FormProps<Collection>> = function ({
    formId,
    data,
    setData,
    usedFormSubmit: {
        handleSubmit,
        submitting,
        register,
        control,
        setValue,
        getValues,
        formState: {errors},
    },
}) {
    const {t} = useTranslation();
    const enabledLocales = useWorkspace(data?.workspace.id)?.enabledLocales;

    const createSaveTranslations = useCreateSaveTranslations({
        data,
        setValue,
        putFn: putCollection,
        setData,
    });

    return (
        <form id={formId} onSubmit={handleSubmit}>
            <FormRow>
                <TranslatedField<Collection>
                    locales={enabledLocales}
                    field={'title'}
                    getData={getValues}
                    getLocales={getLocaleOptions}
                    title={t(
                        'form.collection.title.translate.title',
                        'Translate Title'
                    )}
                    onUpdate={createSaveTranslations('title')}
                >
                    <TextField
                        autoFocus
                        label={t('form.collection.title.label', 'Title')}
                        disabled={submitting}
                        {...register('title', {
                            required: true,
                        })}
                    />
                </TranslatedField>
                <FormFieldErrors field={'title'} errors={errors} />
            </FormRow>
            <FormRow>
                <PrivacyField
                    control={control}
                    name={'privacy'}
                    inheritedPrivacy={data?.inheritedPrivacy}
                />
            </FormRow>
        </form>
    );
};
