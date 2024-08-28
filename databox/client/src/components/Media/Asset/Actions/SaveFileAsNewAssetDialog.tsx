import {useTranslation} from 'react-i18next';
import {useForm} from 'react-hook-form';
import {TextField, Typography} from '@mui/material';
import FormDialog from '../../../Dialog/FormDialog';
import {useFormSubmit} from '@alchemy/api';
import FileCopyIcon from '@mui/icons-material/FileCopy';
import RemoteErrors from '../../../Form/RemoteErrors';
import {Asset, File} from '../../../../types';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useDirtyFormPrompt} from '../../../Dialog/Tabbed/FormTab';
import {toast} from 'react-toastify';
import CollectionTreeWidget from '../../../Form/CollectionTreeWidget';
import {FormFieldErrors, FormRow} from '@alchemy/react-form';
import {postAsset} from '../../../../api/asset';

type FormData = {
    title: string;
    destination: string;
};

export type BaseSaveAsProps = {
    asset: Asset;
    file: File;
    suggestedTitle?: string | undefined;
    integrationId?: string | undefined;
};

type Props = {} & BaseSaveAsProps & StackedModalProps;

export default function SaveFileAsNewAssetDialog({
    asset,
    file,
    open,
    suggestedTitle,
    integrationId,
    modalIndex,
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
            title: suggestedTitle || asset.resolvedTitle,
            destination: undefined,
        },
        onSubmit: async (data: FormData) => {
            const workspace = data.destination.includes('/workspaces/')
                ? data.destination
                : undefined;
            const collection = !workspace ? data.destination : undefined;

            return await postAsset({
                title: data.title,
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
            toast.success(`File is saved`);
            closeModal();
        },
    });
    useDirtyFormPrompt(forbidNavigation);

    const formId = 'save-file-as-new-asset';

    return (
        <FormDialog
            modalIndex={modalIndex}
            title={`Save file as new asset`}
            open={open}
            loading={submitting}
            formId={formId}
            submitIcon={<FileCopyIcon/>}
            submitLabel={'Save'}
        >
            <Typography sx={{mb: 3}}>{``}</Typography>
            <form id={formId} onSubmit={handleSubmit}>
                <FormRow>
                    <TextField
                        autoFocus
                        label={t('form.upload.title.label', 'Title')}
                        disabled={submitting}
                        fullWidth={true}
                        {...register('title')}
                    />
                    <FormFieldErrors field={'title'} errors={errors}/>
                </FormRow>
                <FormRow>
                    <CollectionTreeWidget
                        isSelectable={coll => coll.capabilities.canEdit}
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
                    <FormFieldErrors field={'destination'} errors={errors}/>
                </FormRow>
            </form>
            <RemoteErrors errors={remoteErrors}/>
        </FormDialog>
    );
}
