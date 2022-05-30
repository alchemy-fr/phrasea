import React, {useState} from 'react';
import {StackedModalProps, useModals} from "@mattjennings/react-modal-stack";
import {useTranslation} from "react-i18next";
import {Asset} from "../../../../types";
import {useForm} from "react-hook-form";
import {Typography} from "@mui/material";
import FormDialog from "../../../Dialog/FormDialog";
import useFormSubmit from "../../../../hooks/useFormSubmit";
import FileDownloadIcon from "@mui/icons-material/FileDownload";
import CollectionTreeWidget from "../../../Form/CollectionTreeWidget";

type Props = {
    assetIds: string[];
    onComplete: () => void;
} & StackedModalProps;

type FormData = {
    destination: string | undefined;
}

export default function MoveAssetsDialog({
                                             assetIds,
                                             onComplete,
                                         }: Props) {
    const {t} = useTranslation();
    const [loading, setLoading] = useState(false);
    const {closeModal} = useModals();

    const count = assetIds.length;

    const {
        register,
        handleSubmit,
        setError,
        control,
        formState: {errors}
    } = useForm<FormData>();

    const {
        handleSubmit: onSubmit,
        errors: remoteErrors,
        submitting,
    } = useFormSubmit({
        onSubmit: async (data: FormData) => {
            setLoading(true);

            console.log('data', data);
            onComplete();
            setLoading(false);
        }, onSuccess: () => {
            closeModal();
        },
    });

    const formId = 'move-assets';

    return <FormDialog
        title={t('move_assets.dialog.title', 'Move {{count}} assets', {
            count,
        })}
        loading={loading}
        formId={formId}
        submitIcon={<FileDownloadIcon/>}
        submitLabel={t('move_assets.dialog.submit', 'Move')}
    >
        <Typography sx={{mb: 3}}>
            {t('move_assets.dialog.intro', 'Where do you want to move the selected assets?')}
        </Typography>
        <form
            id={formId}
            onSubmit={handleSubmit(onSubmit(setError))}
        >
            <CollectionTreeWidget
                control={control}
                name={'destination'}
                label={t('form.move_assets.destination.label', 'Destination')}
            />
        </form>
    </FormDialog>
}
