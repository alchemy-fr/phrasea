import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Alert, Typography} from '@mui/material';
import FormDialog from '../../../Dialog/FormDialog';
import {useFormSubmit} from '@alchemy/api';
import CollectionTreeWidget from '../../../Form/CollectionTreeWidget';
import {copyAssets} from '../../../../api/collection';
import {FormFieldErrors} from '@alchemy/react-form';
import FileCopyIcon from '@mui/icons-material/FileCopy';
import RemoteErrors from '../../../Form/RemoteErrors';
import {FormRow} from '@alchemy/react-form';
import SwitchWidget from '../../../Form/SwitchWidget';
import {Asset} from '../../../../types';
import AssetSelection from '../../../AssetList/AssetSelection';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useDirtyFormPromptOutsideRouter} from '../../../Dialog/Tabbed/FormTab';
import {toast} from 'react-toastify';
import {OnSelectionChange} from '../../../AssetList/types';

type FormData = {
    destination: string;
    byReference: boolean;
    withAttributes: boolean;
    withTags: boolean;
};

type Props = {
    assets: Asset[];
    onComplete: () => void;
} & StackedModalProps;

function AssetList({
    assets,
    onSelectionChange,
}: {
    assets: Asset[];
    onSelectionChange: OnSelectionChange<Asset>;
}) {
    const {t} = useTranslation();

    return (
        <div>
            <Typography variant={'body1'}>
                {t(
                    'form.copy_assets.asset_not_linkable.select_for_hard_copy',
                    `You can select the asset you want to duplicate to the destination (hard copy):`
                )}
            </Typography>
            <div
                style={{
                    height: '50vh',
                }}
            >
                <AssetSelection
                    assets={assets}
                    onSelectionChange={onSelectionChange}
                />
            </div>
        </div>
    );
}

export default function CopyAssetsDialog({
    assets,
    onComplete,
    open,
    modalIndex,
}: Props) {
    const [workspaceDest, setWorkspaceDest] = useState<string>();
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const [selectionOW, setSelectionOW] = useState<Asset[]>([]);
    const [selectionP, setSelectionP] = useState<Asset[]>([]);

    const count = assets.length;

    const {
        handleSubmit,
        control,
        watch,
        formState: {errors},
        remoteErrors,
        submitting,
        forbidNavigation,
    } = useFormSubmit({
        defaultValues: {
            destination: '',
            byReference: true,
            withAttributes: true,
            withTags: true,
        },
        onSubmit: (data: FormData) => {
            const finalSelection: string[] = [
                ...assets
                    .filter(
                        a =>
                            a.capabilities.canShare &&
                            workspaceDest &&
                            a.workspace.id === workspaceDest
                    )
                    .map(a => a.id),
                ...selectionOW.map(a => a.id),
                ...selectionP.map(a => a.id),
            ];

            return copyAssets(
                finalSelection,
                data.destination,
                data.byReference,
                {
                    withAttributes: data.withAttributes,
                    withTags: data.withTags,
                }
            );
        },
        onSuccess: () => {
            toast.success(`Assets were copied`);
            closeModal();
            onComplete();
        },
    });
    useDirtyFormPromptOutsideRouter(forbidNavigation);
    const byRef = watch('byReference');

    const nonLinkablePerm: Asset[] = useMemo(
        () => (byRef ? assets.filter(a => !a.capabilities.canShare) : []),
        [byRef, assets]
    );
    const nonLinkableToOtherWS: Asset[] = useMemo(
        () =>
            byRef
                ? assets.filter(
                      a =>
                          a.capabilities.canShare &&
                          workspaceDest &&
                          a.workspace.id !== workspaceDest
                  )
                : [],
        [workspaceDest, nonLinkablePerm]
    );

    const formId = 'copy-assets';

    return (
        <FormDialog
            modalIndex={modalIndex}
            title={t('copy_assets.dialog.title', 'Copy {{count}} assets', {
                count,
            })}
            open={open}
            loading={submitting}
            formId={formId}
            submitIcon={<FileCopyIcon />}
            submitLabel={t('copy_assets.dialog.submit', 'Copy')}
        >
            <Typography sx={{mb: 3}}>
                {t(
                    'copy_assets.dialog.intro',
                    'Where do you want to copy the selected assets?'
                )}
            </Typography>
            <form id={formId} onSubmit={handleSubmit}>
                <FormRow>
                    <SwitchWidget
                        control={control}
                        name={'byReference'}
                        label={t(
                            'copy_assets.form.by_reference.label',
                            'Copy by reference (shortcut)'
                        )}
                    />
                </FormRow>
                <div
                    style={{
                        display: byRef ? 'none' : 'block',
                    }}
                >
                    <FormRow>
                        <SwitchWidget
                            disabled={byRef}
                            control={control}
                            name={'withAttributes'}
                            label={t(
                                'copy_assets.form.with_attributes.label',
                                'Copy attributes'
                            )}
                        />
                    </FormRow>
                    <FormRow>
                        <SwitchWidget
                            disabled={byRef}
                            control={control}
                            name={'withTags'}
                            label={t(
                                'copy_assets.form.with_tags.label',
                                'Copy tags'
                            )}
                        />
                    </FormRow>
                </div>
                <FormRow>
                    <CollectionTreeWidget
                        isSelectable={coll => coll.capabilities.canEdit}
                        onChange={(_nodeId, workspaceId) => {
                            setWorkspaceDest(workspaceId);
                        }}
                        control={control}
                        name={'destination'}
                        rules={{
                            required: true,
                        }}
                        label={t(
                            'form.copy_assets.destination.label',
                            'Destination'
                        )}
                    />
                </FormRow>

                {nonLinkablePerm.length > 0 && (
                    <Alert
                        severity={'warning'}
                        sx={{
                            'flexGrow': 1,
                            '.MuiAlert-message': {
                                flexGrow: 1,
                            },
                        }}
                    >
                        <Typography variant={'body1'}>
                            {t(
                                'form.copy_assets.asset_not_linkable.permission',
                                `The following assets cannot be copied by reference because you don't have sufficient permission.`
                            )}
                        </Typography>
                        <AssetList
                            onSelectionChange={setSelectionP}
                            assets={nonLinkablePerm}
                        />
                    </Alert>
                )}
                {nonLinkableToOtherWS.length > 0 && (
                    <Alert severity={'warning'}>
                        <Typography variant={'body1'}>
                            {t(
                                'form.copy_assets.asset_not_linkable.other_ws',
                                `The following assets cannot be copied by reference in another workspace.`
                            )}
                        </Typography>
                        <AssetList
                            onSelectionChange={setSelectionOW}
                            assets={nonLinkableToOtherWS}
                        />
                    </Alert>
                )}

                <FormFieldErrors field={'destination'} errors={errors} />
            </form>
            <RemoteErrors errors={remoteErrors} />
        </FormDialog>
    );
}
