import React from 'react';
import {useTranslation} from "react-i18next";
import {useForm} from "react-hook-form";
import {Typography} from "@mui/material";
import FormDialog from "../../../Dialog/FormDialog";
import useFormSubmit from "../../../../hooks/useFormSubmit";
import CollectionTreeWidget from "../../../Form/CollectionTreeWidget";
import {moveAssets} from "../../../../api/collection";
import FormFieldErrors from "../../../Form/FormFieldErrors";
import DriveFileMoveIcon from "@mui/icons-material/DriveFileMove";
import RemoteErrors from "../../../Form/RemoteErrors";
import {StackedModalProps, useModals} from "../../../../hooks/useModalStack";
import {useDirtyFormPrompt} from "../../../Dialog/Tabbed/FormTab";

type Props = {
    assetIds: string[];
    workspaceId: string;
    onComplete: () => void;
} & StackedModalProps;

type FormData = {
    destination: string;
}

export default function MoveAssetsDialog({
                                             assetIds,
                                             workspaceId,
                                             onComplete,
    open,
                                         }: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const count = assetIds.length;

    const {
        handleSubmit,
        setError,
        control,
        formState: {errors, isDirty}
    } = useForm<FormData>();
    useDirtyFormPrompt(isDirty);

    const {
        handleSubmit: onSubmit,
        errors: remoteErrors,
        submitting,
    } = useFormSubmit({
        onSubmit: (data: FormData) => moveAssets(assetIds, data.destination),
        onSuccess: () => {
            closeModal();
            onComplete();
        },
    });

    const formId = 'move-assets';

    return <FormDialog
        open={open}
        title={t('move_assets.dialog.title', 'Move {{count}} assets', {
            count,
        })}
        loading={submitting}
        formId={formId}
        submitIcon={<DriveFileMoveIcon/>}
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
                workspaceId={workspaceId}
                control={control}
                name={'destination'}
                rules={{
                    required: true,
                }}
                label={t('form.move_assets.destination.label', 'Destination')}
            />
            <FormFieldErrors field={'destination'} errors={errors}/>
        </form>
        <RemoteErrors errors={remoteErrors}/>
    </FormDialog>
}
