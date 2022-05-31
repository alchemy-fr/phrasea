import React from 'react';
import {StackedModalProps, useModals} from "@mattjennings/react-modal-stack";
import {useTranslation} from "react-i18next";
import {useForm} from "react-hook-form";
import {Checkbox, FormControlLabel, Switch, Typography} from "@mui/material";
import FormDialog from "../../../Dialog/FormDialog";
import useFormSubmit from "../../../../hooks/useFormSubmit";
import CollectionTreeWidget from "../../../Form/CollectionTreeWidget";
import {addAssetToCollection, copyAssets} from "../../../../api/collection";
import FormFieldErrors from "../../../Form/FormFieldErrors";
import FileCopyIcon from "@mui/icons-material/FileCopy";
import RemoteErrors from "../../../Form/RemoteErrors";
import FormRow from "../../../Form/FormRow";
import SwitchWidget from "../../../Form/SwitchWidget";

type Props = {
    assetIds: string[];
    onComplete: () => void;
} & StackedModalProps;

type FormData = {
    destination: string;
    byReference: boolean;
    withAttributes: boolean;
    withTags: boolean;
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
        watch,
        formState: {errors}
    } = useForm<FormData>({
        defaultValues: {
            destination: '',
            byReference: true,
            withAttributes: true,
            withTags: true,
        }
    });

    const byRef = watch('byReference');

    const {
        handleSubmit: onSubmit,
        errors: remoteErrors,
        submitting,
    } = useFormSubmit({
        onSubmit: (data: FormData) => copyAssets(
            assetIds,
            data.destination,
            data.byReference,
            {
                withAttributes: data.withAttributes,
                withTags: data.withTags,
            }
        ),
        onSuccess: () => {
            closeModal();
            onComplete();
        },
    });

    const formId = 'copy-assets';

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
            <FormRow>
                <SwitchWidget
                    control={control}
                    name={'byReference'}
                    label={t('copy_assets.form.by_reference.label', 'Copy by reference (shortcut)')}
                />
            </FormRow>
            <FormRow>
                <SwitchWidget
                    disabled={byRef}
                    control={control}
                    name={'withAttributes'}
                    label={t('copy_assets.form.with_attributes.label', 'Copy attributes')}
                />
            </FormRow>
            <FormRow>
                <SwitchWidget
                    disabled={byRef}
                    control={control}
                    name={'withTags'}
                    label={t('copy_assets.form.with_tags.label', 'Copy tags')}
                />
            </FormRow>
            <FormRow>
                <CollectionTreeWidget
                    control={control}
                    name={'destination'}
                    rules={{
                        required: true,
                    }}
                    label={t('form.copy_assets.destination.label', 'Destination')}
                />
            </FormRow>
            <FormFieldErrors field={'destination'} errors={errors}/>
        </form>
        <RemoteErrors errors={remoteErrors}/>
    </FormDialog>
}
