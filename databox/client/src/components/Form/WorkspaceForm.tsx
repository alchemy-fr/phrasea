import {Hidden, TextField} from '@mui/material';
import {FC} from 'react';
import {Trans, useTranslation} from 'react-i18next';
import {Workspace} from '../../types';
import {FormFieldErrors} from '@alchemy/react-form';
import {FormRow} from '@alchemy/react-form';
import {FormProps} from './types';
import FlagIcon from '@mui/icons-material/Flag';
import IconFormLabel from './IconFormLabel';
import {SortableCollectionWidget} from '@alchemy/react-form';

import Flag from '../Ui/Flag';
import {useDirtyFormPrompt} from '../Dialog/Tabbed/FormTab';
import {CheckboxWidget} from '@alchemy/react-form';

const emptyLocaleItem = '';

export const WorkspaceForm: FC<FormProps<Workspace>> = function ({
    formId,
    usedFormSubmit: {
        register,
        control,
        handleSubmit,
        watch,
        submitting,
        forbidNavigation,
        formState: {errors},
    },
}) {
    const {t} = useTranslation();

    useDirtyFormPrompt(forbidNavigation);

    const locales = watch('enabledLocales');

    return (
        <>
            <form id={formId} onSubmit={handleSubmit}>
                <FormRow>
                    <TextField
                        autoFocus
                        required={true}
                        label={t('form.workspace.title.label', 'Title')}
                        disabled={submitting}
                        {...register('name', {
                            required: true,
                        })}
                    />
                    <FormFieldErrors field={'name'} errors={errors} />
                </FormRow>
                <FormRow>
                    <CheckboxWidget
                        label={t('form.workspace.public.label', 'Public')}
                        control={control}
                        name={'public'}
                        disabled={submitting}
                    />
                    <FormFieldErrors field={'public'} errors={errors} />
                </FormRow>
                <FormRow>
                    <SortableCollectionWidget
                        errors={errors}
                        emptyItem={emptyLocaleItem}
                        control={control}
                        label={
                            <IconFormLabel startIcon={<FlagIcon />}>
                                {t(
                                    'form.workspace.locales.title',
                                    'Workspace locales'
                                )}
                            </IconFormLabel>
                        }
                        path={'enabledLocales'}
                        register={register}
                        addLabel={t('form.workspace.locales.add', 'Add locale')}
                        removeLabel={
                            <Trans
                                t={t}
                                i18nKey="form.workspace.locales.remove"
                            >
                                Remove <Hidden smDown>this locale</Hidden>
                            </Trans>
                        }
                        renderForm={({index, path}) => {
                            return (
                                <FormRow>
                                    <TextField
                                        InputProps={{
                                            startAdornment: (
                                                <Flag
                                                    sx={{
                                                        mr: 1,
                                                    }}
                                                    locale={
                                                        locales![index] || ''
                                                    }
                                                />
                                            ),
                                        }}
                                        label={t(
                                            'form.workspace.locales.label',
                                            'Locale'
                                        )}
                                        placeholder={t(
                                            'form.workspace.locales.placeholder',
                                            'e.g. fr or fr-FR'
                                        )}
                                        {...register(`${path}.${index}` as any)}
                                        required={true}
                                    />
                                </FormRow>
                            );
                        }}
                    />
                </FormRow>
                <FormRow>
                    <SortableCollectionWidget
                        errors={errors}
                        emptyItem={emptyLocaleItem}
                        control={control}
                        label={
                            <IconFormLabel startIcon={<FlagIcon />}>
                                {t(
                                    'form.workspace.fallback_locales.title',
                                    'Fallbacks locales'
                                )}
                            </IconFormLabel>
                        }
                        path={'localeFallbacks'}
                        register={register}
                        addLabel={t(
                            'form.workspace.fallback_locales.add',
                            'Add fallback locale'
                        )}
                        removeLabel={
                            <Trans
                                t={t}
                                i18nKey="form.workspace.fallback_locales.remove"
                            >
                                Remove <Hidden smDown>this locale</Hidden>
                            </Trans>
                        }
                        renderForm={({index, path}) => {
                            return (
                                <FormRow>
                                    <TextField
                                        label={t(
                                            'form.workspace.fallback_locales.label',
                                            'Locale'
                                        )}
                                        {...register(`${path}.${index}` as any)}
                                        required={true}
                                    />
                                </FormRow>
                            );
                        }}
                    />
                </FormRow>
            </form>
        </>
    );
};
