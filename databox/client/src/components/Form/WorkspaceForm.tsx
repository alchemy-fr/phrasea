import {Hidden, TextField} from "@mui/material";
import {useForm} from "react-hook-form";
import React, {FC} from "react";
import {Trans, useTranslation} from "react-i18next";
import {Workspace} from "../../types";
import FormFieldErrors from "./FormFieldErrors";
import FormRow from "./FormRow";
import {FormProps} from "./types";
import FlagIcon from '@mui/icons-material/Flag';
import IconFormLabel from "./IconFormLabel";
import SortableCollectionWidget from "./SortableCollectionWidget";

const emptyLocaleItem = {
    value: '',
};

export const WorkspaceForm: FC<FormProps<Workspace>> = function ({
                                                                     formId,
                                                                     data,
                                                                     onSubmit,
                                                                     submitting,
                                                                 }) {
    const {t} = useTranslation();

    const {
        register,
        control,
        handleSubmit,
        setError,
        formState: {errors}
    } = useForm<any>({
        defaultValues: data,
    });

    return <>
        <form
            id={formId}
            onSubmit={handleSubmit(onSubmit(setError))}
        >
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
                <FormFieldErrors
                    field={'name'}
                    errors={errors}
                />
            </FormRow>
            <FormRow>
                <SortableCollectionWidget
                    emptyItem={emptyLocaleItem}
                    control={control}
                    label={<IconFormLabel startIcon={<FlagIcon/>}>
                        {t('form.workspace.locales.title', 'Workspace locales')}
                    </IconFormLabel>}
                    path={'enabledLocales'}
                    register={register}
                    addLabel={t('form.workspace.locales.add', 'Add locale')}
                    removeLabel={<Trans t={t} i18nKey="form.workspace.locales.remove">
                        Remove <Hidden smDown>this locale</Hidden>
                    </Trans>}
                    renderForm={({index, path}) => {
                        return <FormRow>
                            <TextField
                                label={t('form.workspace.locales.label', 'Locale')}
                                placeholder={t('form.workspace.locales.placeholder', 'Locale')}
                                {...register(`${path}.${index}.value` as any)}
                                required={true}
                            />
                        </FormRow>
                    }}
                />
            </FormRow>
            <FormRow>
                <SortableCollectionWidget
                    emptyItem={emptyLocaleItem}
                    control={control}
                    label={<IconFormLabel startIcon={<FlagIcon/>}>
                        {t('form.workspace.fallback_locales.title', 'Fallbacks locales')}
                    </IconFormLabel>}
                    path={'localeFallbacks'}
                    register={register}
                    addLabel={t('form.workspace.fallback_locales.add', 'Add fallback locale')}
                    removeLabel={<Trans t={t} i18nKey="form.workspace.fallback_locales.remove">
                        Remove <Hidden smDown>this locale</Hidden>
                    </Trans>}
                    renderForm={({index, path}) => {
                        return <FormRow>
                            <TextField
                                label={t('form.workspace.fallback_locales.label', 'Locale')}
                                {...register(`${path}.${index}.value` as any)}
                                required={true}
                            />
                        </FormRow>
                    }}
                />
            </FormRow>
        </form>
    </>
}
