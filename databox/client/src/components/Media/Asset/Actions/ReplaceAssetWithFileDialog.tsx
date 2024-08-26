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
import {useTranslation} from 'react-i18next';

type Props = {
    asset: Asset;
    file: File;
} & StackedModalProps;

export default function ReplaceAssetWithFileDialog({
    asset,
    file,
    open,
    modalIndex,
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();

    const {handleSubmit, remoteErrors, submitting, forbidNavigation} =
        useFormSubmit({
            defaultValues: {},
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
    useDirtyFormPrompt(forbidNavigation);

    const formId = 'save-file-as-new-asset';

    return (
        <FormDialog
            modalIndex={modalIndex}
            title={`Replace asset with file`}
            open={open}
            loading={submitting}
            formId={formId}
            submitIcon={<FileCopyIcon />}
            submitLabel={t('asset.replace.label', `Replace`)}
        >
            <Typography sx={{mb: 3}}>
                {`Please confirm replacing asset.`}
            </Typography>
            <form id={formId} onSubmit={handleSubmit}></form>
            <RemoteErrors errors={remoteErrors} />
        </FormDialog>
    );
}
