import React, {useState} from 'react';
import {Box, Grid} from "@mui/material";
import FileCard from "./FileCard";
import {toast} from "react-toastify";
import {useTranslation} from "react-i18next";
import UploadIcon from '@mui/icons-material/Upload';
import useFormSubmit from "../../hooks/useFormSubmit";
import FormDialog from "../Dialog/FormDialog";
import {UploadData, UploadForm} from "./UploadForm";
import {UploadFiles} from "../../api/uploader/file";
import {StackedModalProps, useModals} from "../../hooks/useModalStack";
import {useNavigationPrompt} from "../../hooks/useNavigationPrompt";
import {nodeNewPrefix} from "../Media/Collection/EditableTree";
import {postCollection} from "../../api/collection";
import {newCollectionPathSeparator, treeViewPathSeparator} from "../Media/Collection/CollectionsTreeView";
import {submitFiles} from "../../lib/upload/uploader";
import moment from "moment";

type Props = {
    files: File[];
    userId: string;
} & StackedModalProps;

type FileWrapper = {
    id: string;
    file: File;
};

export default function UploadModal({
                                        files: initFiles,
                                        userId,
                                        open,
                                    }: Props) {
    const {t} = useTranslation();
    const [files, setFiles] = useState<FileWrapper[]>(initFiles.map((f, i) => ({
        file: f,
        id: i.toString(),
    })));
    const {closeModal} = useModals();
    useNavigationPrompt('Are you sure you want to dismiss upload?', files.length > 0);

    const {
        submitting,
        submitted,
        handleSubmit,
        errors
    } = useFormSubmit({
        onSubmit: async (data: UploadData) => {
            return await submitFiles(userId, {
                files: files.map(f => ({
                    file: f.file,
                    title: f.file.name === 'image.png' ? createPastedImageTitle() : f.file.name,
                    destination: data.destination,
                    privacy: data.privacy,
                })),
            });
        },
        onSuccess: (item) => {
            toast.success(t('form.upload.success', 'Files uploaded!'))
            closeModal(true);
        }
    });

    const onFileRemove = (id: string) => {
        setFiles(p => p.filter(f => f.id !== id));
    };

    const formId = 'upload';

    return <FormDialog
        title={t('form.upload.title', 'Upload')}
        formId={formId}
        open={open}
        loading={submitting}
        errors={errors}
        submitIcon={<UploadIcon/>}
        submitLabel={t('form.upload.submit.title', 'Upload')}
    >
        <Box
            sx={(theme) => ({
                display: 'flex',
                flexWrap: 'wrap',
                bgcolor: theme.palette.grey[100],
                justifyContent: 'start',
                '& > *': {
                    margin: theme.spacing(1),
                    width: 350
                },
                maxHeight: 400,
                overflow: 'auto',
                m: theme.spacing(-2),
                mb: 5,
                p: 0,
                pb: 2,
            })}
        >
            <Grid
                spacing={2}
                container
            >
                {files.map((f) => <Grid
                    item
                    key={f.id}
                >
                    <FileCard
                        file={f.file}
                        onRemove={() => onFileRemove(f.id)}
                    />
                </Grid>)}
            </Grid>
        </Box>
        <UploadForm
            formId={formId}
            onSubmit={handleSubmit}
            submitting={submitting}
            submitted={submitted}
        />
    </FormDialog>
}

function createPastedImageTitle(): string {
    const m = moment();

    return `Pasted-image-${m.format('YYYY-MM-DD_HH-mm-ss')}`;
}
