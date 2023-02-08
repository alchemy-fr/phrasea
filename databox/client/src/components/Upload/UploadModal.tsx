import React, {useState} from 'react';
import {Box, Grid} from "@mui/material";
import FileCard from "./FileCard";
import {toast} from "react-toastify";
import {useTranslation} from "react-i18next";
import UploadIcon from '@mui/icons-material/Upload';
import useFormSubmit from "../../hooks/useFormSubmit";
import FormDialog from "../Dialog/FormDialog";
import {UploadData, UploadForm} from "./UploadForm";
import {StackedModalProps, useModals} from "../../hooks/useModalStack";
import {useNavigationPrompt} from "../../hooks/useNavigationPrompt";
import {submitFiles} from "../../lib/upload/uploader";
import moment from "moment";
import {v4 as uuidv4} from 'uuid';
import UploadDropzone from "./UploadDropzone";
import {CollectionChip, WorkspaceChip} from "../Ui/Chips";

type FileWrapper = {
    id: string;
    file: File;
};

type Props = {
    files: File[];
    userId: string;
    workspaceId?: string;
    collectionId?: string;
    titlePath?: string[];
    workspaceTitle?: string;
} & StackedModalProps;

export default function UploadModal({
    files: initFiles,
    userId,
    workspaceId,
    open,
    workspaceTitle,
    collectionId,
    titlePath,
}: Props) {
    const {t} = useTranslation();
    const [files, setFiles] = useState<FileWrapper[]>(initFiles.map((f, i) => ({
        file: f,
        id: uuidv4().toString(),
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
                    tags: data.tags,
                    title: f.file.name === 'image.png' ? createPastedImageTitle() : f.file.name,
                    destination: collectionId ? `/collections/${collectionId}` : data.destination,
                    privacy: data.privacy,
                })),
            });
        },
        onSuccess: (item) => {
            toast.success(t('form.upload.success', 'Files uploaded!'))
            closeModal(true);
        }
    });

    const onDrop = (acceptedFiles: File[]) => {
        setFiles(p => acceptedFiles.map(file => ({
            id: uuidv4().toString(),
            file,
        })).concat(p));
    };

    const onFileRemove = (id: string) => {
        setFiles(p => p.filter(f => f.id !== id));
    };

    const formId = 'upload';

    const title = workspaceTitle ? (titlePath ? <>
            {t('form.asset_create.title_with_parent', 'Create asset under')}
            {' '}
            <WorkspaceChip label={workspaceTitle}/>
            {titlePath.map((t, i) => <React.Fragment key={i}>
                {' / '}
                <CollectionChip label={t}/>
            </React.Fragment>)}
        </>
        : <>
            {t('form.asset_create.title', 'Create asset in')}
            {' '}
            <WorkspaceChip label={workspaceTitle}/>
        </>) : undefined;

    return <FormDialog
        title={title ?? t('form.upload.title', 'Upload')}
        formId={formId}
        open={open}
        loading={submitting}
        errors={errors}
        submitIcon={<UploadIcon/>}
        submitLabel={t('form.upload.submit.title', 'Upload')}
        submittable={files.length > 0}
    >
        <UploadDropzone
            onDrop={onDrop}
        />
        {files.length > 0 && <Box
            sx={(theme) => ({
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
                {files.map((f) => <Grid
                    item
                    xs={12}
                    md={6}
                    key={f.id}
                >
                    <FileCard
                        file={f.file}
                        onRemove={() => onFileRemove(f.id)}
                    />
                </Grid>)}
            </Grid>
        </Box>}
        <UploadForm
            formId={formId}
            workspaceId={workspaceId}
            onSubmit={handleSubmit}
            submitting={submitting}
            submitted={submitted}
            noDestination={Boolean(workspaceTitle)}
        />
    </FormDialog>
}

function createPastedImageTitle(): string {
    const m = moment();

    return `Pasted-image-${m.format('YYYY-MM-DD_HH-mm-ss')}`;
}
