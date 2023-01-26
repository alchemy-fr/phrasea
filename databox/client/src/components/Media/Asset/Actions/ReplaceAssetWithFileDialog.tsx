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
import {putAsset} from "../../../../api/asset";

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

    const {
        handleSubmit: onSubmit,
        errors: remoteErrors,
        submitting,
        submitted,
    } = useFormSubmit({
        onSubmit: async (data: FormData) => {
            return await putAsset(asset.id, {
                sourceFileId: file.id,
            })
        },
        onSuccess: () => {
            toast.success(`Asset has been replaced`);
            closeModal();
        },
    });
    useDirtyFormPrompt(!submitted && isDirty);

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
