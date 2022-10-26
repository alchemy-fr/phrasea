import {FormGroup, InputLabel, TextField} from "@mui/material";
import {useForm} from "react-hook-form";
import React, {FC} from "react";
import {useTranslation} from "react-i18next";
import {Asset} from "../../types";
import FormFieldErrors from "./FormFieldErrors";
import PrivacyField from "../Ui/PrivacyField";
import FormRow from "./FormRow";
import {FormProps} from "./types";
import TagSelect from "./TagSelect";
import {useDirtyFormPrompt} from "../Dialog/Tabbed/FormTab";

export const AssetForm: FC<{
    workspaceId: string;
} & FormProps<Asset>> = function ({
                                      formId,
                                      data,
                                      onSubmit,
                                      submitting,
                                      submitted,
                                      workspaceId,
                                  }) {
    const {t} = useTranslation();

    const {
        register,
        handleSubmit,
        setError,
        control,
        formState: {errors, isDirty}
    } = useForm<any>({
        defaultValues: {
            ...data,
            tags: data?.tags.map(t => t['@id']) ?? [],
        },
    });
    useDirtyFormPrompt(!submitted && isDirty);

    return <>
        <form
            id={formId}
            onSubmit={handleSubmit(onSubmit(setError))}
        >
            <FormRow>
                <TextField
                    autoFocus
                    required={true}
                    label={t('form.asset.title.label', 'Title')}
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
                <FormGroup>
                    <InputLabel>
                        {t('form.asset.tags.label', 'Tags')}
                    </InputLabel>
                    <TagSelect
                        workspaceId={workspaceId}
                        control={control}
                        name={'tags'}
                    />
                    <FormFieldErrors
                        field={'tags'}
                        errors={errors}
                    />
                </FormGroup>
            </FormRow>
            <FormRow>
                <PrivacyField
                    control={control}
                    name={'privacy'}
                />
            </FormRow>
        </form>
    </>
}
