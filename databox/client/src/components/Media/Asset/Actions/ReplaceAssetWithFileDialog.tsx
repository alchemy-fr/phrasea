import {useForm} from 'react-hook-form';
import {Typography} from '@mui/material';
import FormDialog from '../../../Dialog/FormDialog';
import {useFormSubmit} from '@alchemy/api';
import FileCopyIcon from '@mui/icons-material/FileCopy';
import RemoteErrors from '../../../Form/RemoteErrors';
import {Asset, File} from '../../../../types';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {useDirtyFormPrompt} from '../../../Dialog/Tabbed/FormTab';
import {toast} from 'react-toastify';
import {putAsset} from '../../../../api/asset';

type FormData = {};

type Props = {
    asset: Asset;
    file: File;
} & StackedModalProps;

export default function ReplaceAssetWithFileDialog({asset, file, open}: Props) {
    const {closeModal} = useModals();

    const {
        handleSubmit,
        setError,
        formState: {isDirty},
    } = useForm<FormData>({
        defaultValues: {},
    });

    const {
        handleSubmit: onSubmit,
        errors: remoteErrors,
        submitting,
        submitted,
    } = useFormSubmit({
        onSubmit: async () => {
            return await putAsset(asset.id, {
                sourceFileId: file.id,
            });
        },
        onSuccess: () => {
            toast.success(`Asset has been replaced`);
            closeModal();
        },
    });
    useDirtyFormPrompt(!submitted && isDirty);

    const formId = 'save-file-as-new-asset';

    return (
        <FormDialog
            title={`Replace asset with file`}
            open={open}
            loading={submitting}
            formId={formId}
            submitIcon={<FileCopyIcon />}
            submitLabel={'Replace'}
        >
            <Typography sx={{mb: 3}}>
                {`Please confirm replacing asset.`}
            </Typography>
            <form
                id={formId}
                onSubmit={handleSubmit(onSubmit(setError))}
            ></form>
            <RemoteErrors errors={remoteErrors} />
        </FormDialog>
    );
}
