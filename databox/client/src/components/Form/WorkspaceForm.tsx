import {TextField} from "@mui/material";
import {useForm} from "react-hook-form";
import React, {FC} from "react";
import {useTranslation} from "react-i18next";
import {Workspace} from "../../types";
import FormFieldErrors from "./FormFieldErrors";
import FormRow from "./FormRow";
import {FormProps} from "./types";

export const WorkspaceForm: FC<FormProps<Workspace>> = function ({
                                                                     formId,
                                                                     data,
                                                                     onSubmit,
                                                                     submitting,
                                                                 }) {
    const {t} = useTranslation();

    const {
        register,
        handleSubmit,
        setError,
        formState: {errors}
    } = useForm<any>({
        defaultValues: data,
    });

    return <form
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
    </form>
}
