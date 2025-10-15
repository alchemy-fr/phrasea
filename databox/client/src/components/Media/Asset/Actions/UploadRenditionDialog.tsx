import React from 'react';
import {useTranslation} from 'react-i18next';
import FormDialog from '../../../Dialog/FormDialog';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {toast} from 'react-toastify';
import SingleFileUploadWidget, {
    AssetUploadForm,
} from './SingleFileUploadWidget.tsx';
import UploadIcon from '@mui/icons-material/Upload';
import apiClient from '../../../../api/api-client.ts';
import {putRendition} from '../../../../api/rendition.ts';
import {multipartUpload} from '@alchemy/api/src/multiPartUpload.ts';

type Props = {
    renditionId: string;
    renditionName: string;
} & StackedModalProps;

export default function UploadRenditionDialog({
    renditionId,
    renditionName,
    open,
    modalIndex,
}: Props) {
    const {t} = useTranslation();
    const [uploading, setUploading] = React.useState(false);
    const {closeModal} = useModals();
    const [uploadForm, setUploadForm] = React.useState<
        AssetUploadForm | undefined
    >();

    const upload = async () => {
        if (!uploadForm) {
            return;
        }
        setUploading(true);
        try {
            if (!uploadForm.file) {
                // TODO support URL
                return;
            }
            const multipart = await multipartUpload(apiClient, uploadForm.file);
            await putRendition(renditionId, {
                multipart,
            });

            toast.success(
                t(
                    'upload_rendition.dialog.success',
                    'Rendition has been uploaded successfully.'
                )
            );
            closeModal();
        } finally {
            setUploading(false);
        }
    };

    return (
        <FormDialog
            modalIndex={modalIndex}
            title={t('upload_rendition.dialog.title', {
                defaultValue: 'Upload Rendition {{renditionName}}',
                renditionName,
            })}
            open={open}
            loading={uploading}
            onSave={upload}
            submitIcon={<UploadIcon />}
            submitLabel={t(
                'upload_rendition.dialog.submit',
                'Upload Rendition'
            )}
            submittable={!!uploadForm}
        >
            <SingleFileUploadWidget onUpload={setUploadForm} />
        </FormDialog>
    );
}
