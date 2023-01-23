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
};


type Props = {
    asset: Asset;
    file: File;
} & StackedModalProps;

export default function ReplaceAssetWithFileDialog({
                                                     asset,
                                                     file,
                                                     open,
                                                 }: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const {
        handleSubmit,
        setError,
        formState: {errors, isDirty}
    } = useForm<FormData>({
        defaultValues: {
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
            toast.success(`Asset has been replaced`);
            closeModal();
        },
    });

    const formId = 'save-file-as-new-asset';

    return <FormDialog
        title={`Replace asset with file`}
        open={open}
        loading={submitting}
        formId={formId}
        submitIcon={<FileCopyIcon/>}
        submitLabel={'Replace'}
    >
        <Typography sx={{mb: 3}}>
            {`Please confirm replacing asset.`}
        </Typography>
        <form
            id={formId}
            onSubmit={handleSubmit(onSubmit(setError))}
        >
        </form>
        <RemoteErrors errors={remoteErrors}/>
    </FormDialog>
}
