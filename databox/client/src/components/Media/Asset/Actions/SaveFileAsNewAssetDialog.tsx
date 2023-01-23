import React, {useState} from 'react';
import {useTranslation} from "react-i18next";
import {useForm} from "react-hook-form";
import {TextField, Typography} from "@mui/material";
import FormDialog from "../../../Dialog/FormDialog";
import useFormSubmit from "../../../../hooks/useFormSubmit";
import FileCopyIcon from "@mui/icons-material/FileCopy";
import RemoteErrors from "../../../Form/RemoteErrors";
import {Asset, File} from "../../../../types";
import {StackedModalProps, useModals} from "../../../../hooks/useModalStack";
import {useDirtyFormPrompt} from "../../../Dialog/Tabbed/FormTab";
import {toast} from "react-toastify";
import CollectionTreeWidget from "../../../Form/CollectionTreeWidget";
import FormFieldErrors from "../../../Form/FormFieldErrors";
import FormRow from "../../../Form/FormRow";

type FormData = {
    title: string;
    destinations: string[];
};


type Props = {
    asset: Asset;
    file: File;
} & StackedModalProps;

export default function SaveFileAsNewAssetDialog({
                                                     asset,
                                                     file,
                                                     open,
                                                 }: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const {
        handleSubmit,
        setError,
        control,
        register,
        formState: {errors, isDirty}
    } = useForm<FormData>({
        defaultValues: {
            title: asset.resolvedTitle,
            destinations: [],
        }
    });
    useDirtyFormPrompt(isDirty);

    const {
        handleSubmit: onSubmit,
        errors: remoteErrors,
        submitting,
    } = useFormSubmit({
        onSubmit: async (data: FormData) => {
            return data;
        },
        onSuccess: () => {
            toast.success(`File is saved`);
            closeModal();
        },
    });

    const formId = 'save-file-as-new-asset';

    return <FormDialog
        title={`Save file as new asset`}
        open={open}
        loading={submitting}
        formId={formId}
        submitIcon={<FileCopyIcon/>}
        submitLabel={'Save'}
    >
        <Typography sx={{mb: 3}}>
            {``}
        </Typography>
        <form
            id={formId}
            onSubmit={handleSubmit(onSubmit(setError))}
        >
            <FormRow>
                <TextField
                    autoFocus
                    label={t('form.upload.title.label', 'Title')}
                    disabled={submitting}
                    fullWidth={true}
                    {...register('title')}
                />
                <FormFieldErrors
                    field={'title'}
                    errors={errors}
                />
            </FormRow>
            <FormRow>
                <CollectionTreeWidget
                    control={control}
                    rules={{
                        required: true,
                    }}
                    name={'destinations'}
                    label={t('form.upload.destinations.label', 'Destinations')}
                    multiple={true}
                    required={true}
                    allowNew={true}
                />
                <FormFieldErrors
                    field={'destinations'}
                    errors={errors}
                />
            </FormRow>
        </form>
        <RemoteErrors errors={remoteErrors}/>
    </FormDialog>
}
