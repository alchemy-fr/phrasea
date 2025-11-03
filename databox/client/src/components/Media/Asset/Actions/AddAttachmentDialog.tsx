import React from 'react';
import {useTranslation} from 'react-i18next';
import FormDialog from '../../../Dialog/FormDialog';
import {Asset, AssetAttachment} from '../../../../types';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {toast} from 'react-toastify';
import SingleFileUploadWidget, {
    FileUploadForm,
} from './SingleFileUploadWidget.tsx';
import UploadIcon from '@mui/icons-material/Upload';
import apiClient from '../../../../api/api-client.ts';
import {multipartUpload} from '@alchemy/api/src/multiPartUpload.ts';
import {postAttachment} from '../../../../api/attachment.ts';

type Props = {
    asset: Asset;
    onAttachmentAdded?: (attachment: AssetAttachment) => void;
} & StackedModalProps;

export default function AddAttachmentDialog({
    asset,
    onAttachmentAdded,
    open,
    modalIndex,
}: Props) {
    const {t} = useTranslation();
    const [uploading, setUploading] = React.useState(false);
    const {closeModal} = useModals();
    const [uploadForm, setUploadForm] = React.useState<
        FileUploadForm | undefined
    >();

    const upload = async () => {
        if (!uploadForm) {
            return;
        }
        setUploading(true);
        try {
            const res = await (async () => {
                if (!uploadForm.file) {
                    return await postAttachment({
                        assetId: asset.id,
                        sourceFile: {
                            url: uploadForm.url,
                            importFile: uploadForm.importFile,
                        },
                    });
                }
                const multipart = await multipartUpload(
                    apiClient,
                    uploadForm.file
                );
                return await postAttachment({
                    assetId: asset.id,
                    multipart,
                });
            })();

            toast.success(
                t(
                    'attachment.dialog.add.success',
                    'New attachment has been added.'
                )
            );

            onAttachmentAdded?.(res);

            closeModal();
        } finally {
            setUploading(false);
        }
    };

    return (
        <FormDialog
            modalIndex={modalIndex}
            title={t('attachment.dialog.add.title', 'Add attachment to Asset')}
            open={open}
            loading={uploading}
            onSave={upload}
            submitIcon={<UploadIcon />}
            submitLabel={t('attachment.dialog.add.submit', 'Attach')}
            submittable={!!uploadForm}
        >
            <SingleFileUploadWidget onUpload={setUploadForm} />
        </FormDialog>
    );
}
