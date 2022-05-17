import React, {useContext, useEffect, useState} from 'react';
import {Box, Grid} from "@mui/material";
import {Asset, Workspace} from "../../types";
import {getWorkspaces} from "../../api/collection";
import FileCard from "./FileCard";
import {toast} from "react-toastify";
import {useTranslation} from "react-i18next";
import {UserContext} from "../Security/UserContext";
import {StackedModalProps, useModals} from "@mattjennings/react-modal-stack";
import UploadIcon from '@mui/icons-material/Upload';
import useFormSubmit from "../../hooks/useFormSubmit";
import FormDialog from "../Dialog/FormDialog";
import {UploadData, UploadForm} from "./UploadForm";
import {UploadFiles} from "../../api/file";

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
}: Props) {
    const {t} = useTranslation();
    const [files, setFiles] = useState<FileWrapper[]>(initFiles.map((f, i) => ({
        file: f,
        id: i.toString(),
    })));
    const {closeModal} = useModals();

    useEffect(() => {
        if (files.length === 0) {
            closeModal();
        }
    }, [closeModal, files]);

    const {
        submitting,
        handleSubmit,
        errors
    } = useFormSubmit({
        onSubmit: async (data: UploadData) => {
            return await UploadFiles(userId, files.map(f => f.file), {
                destinations: data.destinations!,
            });
        },
        onSuccess: (item) => {
            toast.success(t('form.upload.success', 'Files uploaded!'))
            closeModal();
        }
    });

    const onFileRemove = (id: string) => {
        setFiles(p => p.filter(f => f.id !== id));
    };

    const formId = 'upload';

    return <FormDialog
        title={t('form.upload.title', 'Upload')}
        formId={formId}
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
                xs
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
        />
    </FormDialog>
}
