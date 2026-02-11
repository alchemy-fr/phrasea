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
import {postAttachment} from '../../../../api/attachment.ts';
import {
    extractTitleFromUrl,
    getAssetTitleFromFile,
    postAsset,
    uploadAsset,
} from '../../../../api/asset.ts';
import {EntityName} from '../../../../api/types.ts';

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
                const workspaceIri = `/${EntityName.Workspace}/${asset.workspace.id}`;
                const attachment = uploadForm.file
                    ? await uploadAsset({
                          file: uploadForm.file,
                          asset: {
                              title: getAssetTitleFromFile(uploadForm.file, t),
                              workspace: workspaceIri,
                          },
                      })
                    : await postAsset({
                          title: extractTitleFromUrl(uploadForm.url),
                          sourceFile: {
                              url: uploadForm.url,
                              importFile: uploadForm.importFile,
                          },
                          workspace: workspaceIri,
                      });

                return await postAttachment({
                    assetId: asset.id,
                    attachmentId: attachment.id,
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
