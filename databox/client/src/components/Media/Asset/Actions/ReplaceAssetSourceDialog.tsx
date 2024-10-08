import React from 'react';
import {useTranslation} from 'react-i18next';
import FormDialog from '../../../Dialog/FormDialog';
import {Asset} from '../../../../types';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import UploadDropzone from "../../../Upload/UploadDropzone.tsx";
import {UploadFiles} from "../../../../api/uploader/file.ts";
import {toast} from "react-toastify";
import {Box, Grid} from "@mui/material";
import FileCard from "../../../Upload/FileCard.tsx";
import {prepareAssetSubstitution} from "../../../../api/asset.ts";

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
    const [file, setFile] = React.useState<File>();

    const onDrop = (acceptedFiles: File[]) => {
        setFile(acceptedFiles[0]);
    };

    const upload = async () => {
        if (!file) {
            return;
        }
        setUploading(true);
        try {
            const data = await prepareAssetSubstitution(asset.id);
            await UploadFiles(
                [{
                    file,
                    data: {
                        targetAsset: data.id,
                        uploadToken: data.pendingUploadToken,
                    }
                }]
            );

            toast.success(t('replace_asset.dialog.success', 'New asset source file has been uploaded and will be replaced soon.'));
            closeModal();
        } finally {
            setUploading(false);
        }

    }

    return (
        <FormDialog
            modalIndex={modalIndex}
            title={t('replace_asset.dialog.title', 'Substitute asset source file')}
            open={open}
            loading={uploading}
            onSave={upload}
            submittable={!!file}
        >
            {!file ? <UploadDropzone onDrop={onDrop}/> : (
                <Box
                    sx={theme => ({
                        bgcolor: theme.palette.grey[100],
                        maxHeight: 400,
                        overflow: 'auto',
                        p: 1,
                    })}
                >
                    <Grid
                        container
                        rowSpacing={1}
                        columnSpacing={{xs: 1, sm: 2, md: 3}}
                    >
                            <Grid item xs={12} md={6}>
                                <FileCard
                                    file={file}
                                    onRemove={() => setFile(undefined)}
                                />
                            </Grid>
                    </Grid>
                </Box>
            )}
        </FormDialog>
    );
}
