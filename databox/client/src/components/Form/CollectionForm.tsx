import {TextField} from "@mui/material";
import {useForm} from "react-hook-form";
import React, {FC} from "react";
import {useTranslation} from "react-i18next";
import {Collection} from "../../types";
import FormFieldErrors from "./FormFieldErrors";
import PrivacyField from "../Ui/PrivacyField";
import FormRow from "./FormRow";
import {FormProps} from "./types";
import TagFilterRules from "../Media/TagFilterRule/TagFilterRules";

export const CollectionForm: FC<FormProps<Collection>> = function ({
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
        control,
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

        {data && <>
            <h4>Tag filter rules</h4>
            <TagFilterRules
                id={data.id}
                workspaceId={data.workspace.id}
                type={'collection'}
            />
        </>}
    </>
}
