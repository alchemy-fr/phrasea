import React, {useState} from 'react';
import {StackedModalProps, useModals} from "@mattjennings/react-modal-stack";
import {useTranslation} from "react-i18next";
import {useForm} from "react-hook-form";
import {Alert, Checkbox, FormControlLabel, Typography} from "@mui/material";
import FormDialog from "../../../Dialog/FormDialog";
import useFormSubmit from "../../../../hooks/useFormSubmit";
import CollectionTreeWidget from "../../../Form/CollectionTreeWidget";
import {copyAssets} from "../../../../api/collection";
import FormFieldErrors from "../../../Form/FormFieldErrors";
import FileCopyIcon from "@mui/icons-material/FileCopy";
import RemoteErrors from "../../../Form/RemoteErrors";
import FormRow from "../../../Form/FormRow";
import SwitchWidget from "../../../Form/SwitchWidget";
import {Asset} from "../../../../types";
import AssetSelection from "../AssetSelection";

type Props = {
    assets: Asset[];
    onComplete: () => void;
} & StackedModalProps;

type FormData = {
    destination: string;
    byReference: boolean;
    withAttributes: boolean;
    withTags: boolean;
}

function AssetList({
                       assets,
                   }: {
    assets: Asset[]
}) {
    const [selection, setSelection] = useState<string[]>([]);
    const {t} = useTranslation();

    return <div>
        <Typography variant={'body1'}>
            {t('form.copy_assets.asset_not_linkable.select_for_hard_copy', `You can select the asset you want to duplicate to the destination (hard copy):`)}
        </Typography>
        <FormControlLabel
            control={<Checkbox
                checked={selection.length === assets.length}
                onChange={(e, checked) => {
                    setSelection(checked ? assets.map(a => a.id) : []);
                }}
            />}
            label={`${t('form.copy_assets.asset_not_linkable.toggle_select_all', 'Select/Unselect all')} (${assets.length})`}
            labelPlacement="end"
        />
        <AssetSelection
            style={{
                maxHeight: 300,
                overflow: 'auto',
            }}
            assets={assets}
            onSelectionChange={setSelection}
        />
    </div>
}

export default function CopyAssetsDialog({
                                             assets,
                                             onComplete,
                                         }: Props) {
    const [workspaceDest, setWorkspaceDest] = useState<string>();
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const count = assets.length;

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
            assets.map(a => a.id),
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

    const nonLinkablePerm: Asset[] = byRef ? assets.filter(a => !a.capabilities.canEdit) : [];
    const nonLinkableToOtherWS: Asset[] = byRef ? assets
            .filter(a => a.capabilities.canEdit && workspaceDest && a.workspace.id !== workspaceDest)
        : [];

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
            <div style={{
                display: byRef ? 'none' : 'block',
            }}>
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
            </div>
            <FormRow>
                <CollectionTreeWidget
                    onChange={(nodeId, workspaceId) => {
                        setWorkspaceDest(workspaceId);
                    }}
                    control={control}
                    name={'destination'}
                    rules={{
                        required: true,
                    }}
                    label={t('form.copy_assets.destination.label', 'Destination')}
                />
            </FormRow>

            {nonLinkablePerm.length > 0 && <Alert
                severity={'warning'}
                sx={{
                    flexGrow: 1,
                    '.MuiAlert-message': {
                        flexGrow: 1,
                    }
                }}
            >
                <Typography variant={'body1'}>
                    {t('form.copy_assets.asset_not_linkable.permission', `The following assets cannot be copied by reference because you don't have sufficient permission.`)}
                </Typography>
                <AssetList
                    assets={nonLinkablePerm}
                />
            </Alert>}
            {nonLinkableToOtherWS.length > 0 && <Alert
                severity={'warning'}
            >
                <Typography variant={'body1'}>
                    {t('form.copy_assets.asset_not_linkable.other_ws', `The following assets cannot be copied by reference in another workspace.`)}
                </Typography>
                <AssetList
                    assets={nonLinkableToOtherWS}
                />
            </Alert>}

            <FormFieldErrors field={'destination'} errors={errors}/>
        </form>
        <RemoteErrors errors={remoteErrors}/>
    </FormDialog>
}
