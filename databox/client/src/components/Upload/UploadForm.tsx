import {TextField} from "@mui/material";
import {useForm} from "react-hook-form";
import React, {FC} from "react";
import {useTranslation} from "react-i18next";
import FormRow from "../Form/FormRow";
import FormFieldErrors from "../Form/FormFieldErrors";
import {FormProps} from "../Form/types";
import CollectionTreeWidget from "../Form/CollectionTreeWidget";

export type UploadData = {
    title: string;
    destinations: string[];
};

export const UploadForm: FC<FormProps<UploadData>> = function ({
                                                                   formId,
                                                                   data,
                                                                   onSubmit,
                                                                   submitting,
                                                               }) {
    const {t} = useTranslation();

    const {
        register,
        handleSubmit,
        control,
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
                label={t('form.upload.title.label', 'Title')}
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
            <CollectionTreeWidget
                control={control}
                name={'destinations'}
                label={t('form.upload.destinations.label', 'Destinations')}
            />
            <FormFieldErrors
                field={'destinations'}
                errors={errors}
            />
        </FormRow>
    </form>
}
