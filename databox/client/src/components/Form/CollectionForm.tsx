import {TextField} from "@mui/material";
import {useForm} from "react-hook-form";
import React, {FC} from "react";
import {useTranslation} from "react-i18next";
import {Collection} from "../../types";
import FormFieldErrors from "./FormFieldErrors";
import PrivacyField from "../Ui/PrivacyField";
import FormRow from "./FormRow";
import {FormProps} from "./types";
import {useDirtyFormPrompt} from "../Dialog/Tabbed/FormTab";

export const CollectionForm: FC<FormProps<Collection>> = function ({
    formId,
    data,
    onSubmit,
    submitting,
    submitted,
}) {
    const {t} = useTranslation();

    const {
        register,
        handleSubmit,
        setError,
        control,
        formState: {errors, isDirty}
    } = useForm<any>({
        defaultValues: data ?? {
            title: '',
            privacy: 0,
        },
    });
    useDirtyFormPrompt(!submitted && isDirty);

    return <form
        id={formId}
        onSubmit={handleSubmit(onSubmit(setError))}
    >
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
            <FormFieldErrors
                field={'title'}
                errors={errors}
            />
        </FormRow>
        <FormRow>
            <PrivacyField
                control={control}
                name={'privacy'}
            />
        </FormRow>
    </form>
}
