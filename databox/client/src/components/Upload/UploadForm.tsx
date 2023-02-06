import {useForm} from "react-hook-form";
import React, {FC} from "react";
import {useTranslation} from "react-i18next";
import FormRow from "../Form/FormRow";
import FormFieldErrors from "../Form/FormFieldErrors";
import {FormProps} from "../Form/types";
import CollectionTreeWidget from "../Form/CollectionTreeWidget";
import PrivacyField from "../Ui/PrivacyField";
import {Privacy} from "../../api/privacy";

export type UploadData = {
    destination: string;
    privacy: number;
};

export const UploadForm: FC<FormProps<UploadData>> = function ({
                                                                   formId,
                                                                   onSubmit,
                                                                   submitting,
                                                               }) {
    const {t} = useTranslation();

    const {
        handleSubmit,
        control,
        setError,
        formState: {errors}
    } = useForm<any>({
        defaultValues: {
            destination: null,
            privacy: Privacy.Secret
        },
    });

    return <form
        id={formId}
        onSubmit={handleSubmit(onSubmit(setError))}
    >
        <FormRow>
            <CollectionTreeWidget
                control={control}
                rules={{
                    required: true,
                }}
                name={'destination'}
                label={t('form.upload.destination.label', 'Destination')}
                required={true}
                allowNew={true}
                disabled={submitting}
            />
            <FormFieldErrors
                field={'destination'}
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
