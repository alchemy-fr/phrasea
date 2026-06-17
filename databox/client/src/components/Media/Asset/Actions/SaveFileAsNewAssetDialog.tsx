import {useTranslation} from 'react-i18next';
import {useForm} from 'react-hook-form';
import {TextField, Typography} from '@mui/material';
import FormDialog from '../../../Dialog/FormDialog';
import {useFormSubmit} from '@alchemy/api';
import FileCopyIcon from '@mui/icons-material/FileCopy';
import {RemoteErrors} from '@alchemy/react-form';
import {Asset, ApiFile} from '../../../../types';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useDirtyFormPrompt} from '@alchemy/phrasea-framework';
import {toast} from 'react-toastify';
import CollectionTreeWidget from '../../../Form/CollectionTreeWidget';
import {FormFieldErrors, FormRow} from '@alchemy/react-form';
import {postAsset} from '../../../../api/asset';

type FormData = {
    name: string;
    destination: string;
};

export type BaseSaveAsProps = {
    asset: Asset;
    file: ApiFile;
    suggestedName?: string | undefined;
    integrationId?: string | undefined;
};

type Props = {} & BaseSaveAsProps & StackedModalProps;

export default function SaveFileAsNewAssetDialog({
    asset,
    file,
    suggestedName,
    integrationId,
    ...modalProps
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const {} = useForm<FormData>({});

    const {
        handleSubmit,
        control,
        register,
        formState: {errors},
        remoteErrors,
        submitting,
        forbidNavigation,
    } = useFormSubmit({
        defaultValues: {
            name: suggestedName || asset.name,
            destination: undefined,
        },
        onSubmit: async (data: FormData) => {
            const workspace = data.destination.includes('/workspaces/')
                ? data.destination
                : undefined;
            const collection = !workspace ? data.destination : undefined;

            return await postAsset({
                name: data.name,
                collection,
                workspace,
                sourceFileId: file.id,
                relationship: {
                    source: asset.id,
                    type: 'parent',
                    sourceFile: file.id,
                    integration: integrationId,
                },
            });
        },
        onSuccess: () => {
            toast.success(
                t(
                    'save_file_as_new_asset_dialog.file_is_saved',
                    `File is saved`
                )
            );
            closeModal();
        },
    });
    useDirtyFormPrompt(forbidNavigation, modalProps.modalIndex);

    const formId = 'save-file-as-new-asset';

    return (
        <FormDialog
            {...modalProps}
            title={t(
                'save_file_as_new_asset_dialog.save_file_as_new_asset',
                `Save file as new asset`
            )}
            loading={submitting}
            formId={formId}
            submitIcon={<FileCopyIcon />}
            submitLabel={t('save_file_as_new_asset_dialog.save', `Save`)}
        >
            <Typography sx={{mb: 3}}>{``}</Typography>
            <form id={formId} onSubmit={handleSubmit}>
                <FormRow>
                    <TextField
                        autoFocus
                        label={t(
                            'save_file_as_new_asset_dialog.name.label',
                            'Name'
                        )}
                        disabled={submitting}
                        fullWidth={true}
                        {...register('name')}
                    />
                    <FormFieldErrors field={'name'} errors={errors} />
                </FormRow>
                <FormRow>
                    <CollectionTreeWidget
                        isSelectable={node =>
                            node.data.capabilities.createAsset
                        }
                        control={control}
                        rules={{
                            required: true,
                        }}
                        name={'destination'}
                        label={t(
                            'form.save_as.destination.label',
                            'Destination'
                        )}
                        multiple={false}
                        required={true}
                        workspaceId={asset.workspace.id}
                    />
                    <FormFieldErrors field={'destination'} errors={errors} />
                </FormRow>
            </form>
            <RemoteErrors errors={remoteErrors} />
        </FormDialog>
    );
}
