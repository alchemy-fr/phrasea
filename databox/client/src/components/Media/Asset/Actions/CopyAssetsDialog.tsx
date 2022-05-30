import React from 'react';
import {StackedModalProps, useModals} from "@mattjennings/react-modal-stack";
import {useTranslation} from "react-i18next";
import {useForm} from "react-hook-form";
import {Typography} from "@mui/material";
import FormDialog from "../../../Dialog/FormDialog";
import useFormSubmit from "../../../../hooks/useFormSubmit";
import CollectionTreeWidget from "../../../Form/CollectionTreeWidget";
import {addAssetToCollection} from "../../../../api/collection";
import FormFieldErrors from "../../../Form/FormFieldErrors";
import FileCopyIcon from "@mui/icons-material/FileCopy";
import RemoteErrors from "../../../Form/RemoteErrors";

type Props = {
    assetIds: string[];
    onComplete: () => void;
} & StackedModalProps;

type FormData = {
    destination: string;
}

export default function CopyAssetsDialog({
                                             assetIds,
                                             onComplete,
                                         }: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const count = assetIds.length;

    const {
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
            await Promise.all(assetIds
                .map(id => addAssetToCollection(data.destination, `/assets/${id}`)));
        }, onSuccess: () => {
            closeModal();
            onComplete();
        },
    });

    const formId = 'move-assets';

    return <FormDialog
        title={t('copy_assets.dialog.title', 'Copy {{count}} assets', {
            count,
        })}
        loading={submitting}
        formId={formId}
        submitIcon={<FileCopyIcon/>}
        submitLabel={t('copy_assets.dialog.submit', 'Copy')}
    >
        <Typography sx={{mb: 3}}>
            {t('copy_assets.dialog.intro', 'Where do you want to copy the selected assets?')}
        </Typography>
        <form
            id={formId}
            onSubmit={handleSubmit(onSubmit(setError))}
        >
            <CollectionTreeWidget
                control={control}
                name={'destination'}
                rules={{
                    required: true,
                }}
                label={t('form.copy_assets.destination.label', 'Destination')}
            />
            <FormFieldErrors field={'destination'} errors={errors}/>
        </form>
        <RemoteErrors errors={remoteErrors}/>
    </FormDialog>
}
