import React from 'react';
import {useTranslation} from 'react-i18next';
import FormDialog from '../../../Dialog/FormDialog';
import {Asset} from '../../../../types';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {UploadFiles} from '../../../../api/uploader/file.ts';
import {toast} from 'react-toastify';
import {prepareAssetSubstitution} from '../../../../api/asset.ts';
import SingleFileUploadWidget, {
    AssetUploadForm,
} from './SingleFileUploadWidget.tsx';
import UploadIcon from '@mui/icons-material/Upload';

type Props = {
    asset: Asset;
} & StackedModalProps;

export default function ReplaceAssetSourceDialog({
    asset,
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
            const data = await prepareAssetSubstitution(asset.id);
            await UploadFiles([
                {
                    ...uploadForm,
                    data: {
                        targetAsset: data.id,
                        uploadToken: data.pendingUploadToken,
                    },
                },
            ]);

            toast.success(
                t(
                    'replace_asset.dialog.success',
                    'New asset source file has been uploaded and will be replaced soon.'
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
            title={t(
                'replace_asset.dialog.title',
                'Substitute asset source file'
            )}
            open={open}
            loading={uploading}
            onSave={upload}
            submitIcon={<UploadIcon />}
            submitLabel={t('replace_asset.dialog.submit', 'Substitute')}
            submittable={!!uploadForm}
        >
            <SingleFileUploadWidget onUpload={setUploadForm} />
        </FormDialog>
    );
}
