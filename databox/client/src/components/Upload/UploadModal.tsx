import React, {useEffect, useRef, useState} from 'react';
import {UploadFiles} from "../../api/file";
import {Box, Button, TextField} from "@mui/material";
import {Workspace} from "../../types";
import {getWorkspaces} from "../../api/collection";
import FileCard from "./FileCard";
import AppDialog from "../Layout/AppDialog";
import {useForm} from "react-hook-form";
import {toast} from "react-toastify";
import {useTranslation} from "react-i18next";
import {mapApiErrors} from "../../lib/form";
import FormError from "../Form/FormError";

type Props = {
    userId: string;
    onClose: () => void;
    files: File[];
}

type UploadData = {
    title: string;
    destinations: string[];
};

export default function UploadModal({userId, files, onClose}: Props) {
    const {t} = useTranslation();
    const formRef = useRef<HTMLFormElement>();
    const [workspaces, setWorkspaces] = useState<Workspace[]>();
    const [loading, setLoading] = useState(false);
    const [remoteError, setRemoteError] = useState<string | undefined>();
    const [data, setData] = useState<UploadData>({
        title: '',
        destinations: [],
    });

    useEffect(() => {
        getWorkspaces().then(setWorkspaces);
    }, []);

    const {
        register,
        handleSubmit,
        setError,
        formState: {errors}
    } = useForm<UploadData>({
        defaultValues: data,
    });

    const onSubmit = async (data: UploadData): Promise<void> => {
        setRemoteError(undefined);
        setLoading(true);
        try {
            await UploadFiles(userId, files, {
                destinations: data.destinations!,
            });
            toast.success(t('form.file_upload.uploaded', 'Files uploaded!'));
            onClose();
        } catch (e: any) {
            mapApiErrors(e, setError);
        }
    }

    return <AppDialog
        loading={loading}
        onClose={onClose}
        title={`Upload`}
        actions={() => <>
            <Button
                onClick={onClose}
                className={'btn-secondary'}
            >
                Cancel
            </Button>
            <Button
                onClick={() => formRef.current!.submitForm()}
                className={'btn-primary'}
            >
                Upload
            </Button>
        </>}
    >
        <Box
            sx={(theme) => ({
                display: 'flex',
                flexWrap: 'wrap',
                justifyContent: 'start',
                '& > *': {
                    margin: theme.spacing(1),
                    width: 350
                },
                maxHeight: 400,
                overflow: 'auto',
            })}
        >
            {files.map((f, i) => <FileCard
                key={i}
                file={f}
            />)}
        </Box>
        {workspaces && <form onSubmit={handleSubmit(onSubmit)}>
            <div className="form-group">
                <TextField
                    label={t('form.upload_asset.title.label', 'Asset title')}
                    {...register('title', {
                        required: true
                    })}
                />
                {errors.title && <FormError>{errors.title.message}</FormError>}
            </div>
            {/*<Field*/}
            {/*    name="destinations"*/}
            {/*>*/}
            {/*    {({field, form: {errors, setFieldValue}}: FieldProps) => {*/}
            {/*        return <FormControl*/}
            {/*            variant="outlined"*/}
            {/*        >*/}
            {/*            <label>*/}
            {/*                Where?*/}
            {/*            </label>*/}
            {/*            <CollectionsTreeView*/}
            {/*                onChange={(selection) => setFieldValue(field.name, selection)}*/}
            {/*                workspaces={workspaces}/>*/}
            {/*            {errors.destinations && <div className="error text-danger">{errors.destinations}</div>}*/}
            {/*        </FormControl>*/}
            {/*    }}*/}
            {/*</Field>*/}

            {(remoteError) && <FormError>
                {remoteError || ''}
            </FormError>}
        </form>}
    </AppDialog>
}
