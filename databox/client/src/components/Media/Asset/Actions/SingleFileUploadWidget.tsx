import UploadDropzone from '../../../Upload/UploadDropzone.tsx';
import {Box, Button, Checkbox, InputLabel, TextField} from '@mui/material';
import React from 'react';
import {useTranslation} from 'react-i18next';
import LinkIcon from '@mui/icons-material/Link';
import {validateUrl} from '@alchemy/core';
import FileToUploadCard from '../../../Upload/FileToUploadCard.tsx';
import {FileOrUrl} from '../../../../api/file.ts';
import KeyboardArrowLeftIcon from '@mui/icons-material/KeyboardArrowLeft';

export type FileUploadForm = FileOrUrl;

type Props = {
    onUpload?: (upload: FileUploadForm | undefined) => void;
};

export default function SingleFileUploadWidget({onUpload}: Props) {
    const [file, setFile] = React.useState<File | undefined>();
    const [urlMode, setUrlMode] = React.useState(false);
    const [importFile, setImportFile] = React.useState(false);
    const [url, setUrl] = React.useState<string | undefined>();
    const {t} = useTranslation();

    React.useEffect(() => {
        if (!onUpload) {
            return;
        }
        if (!urlMode && file) {
            onUpload({file} as FileUploadForm);
        } else if (urlMode && url && validateUrl(url)) {
            onUpload({
                url: url!,
                importFile,
            });
        } else {
            onUpload(undefined);
        }
    }, [onUpload, urlMode, url, importFile, file]);

    const onDrop = (acceptedFiles: File[]) => {
        setFile(acceptedFiles[0]);
    };

    if (urlMode) {
        return (
            <>
                <Button
                    variant={'text'}
                    onClick={() => setUrlMode(false)}
                    startIcon={<KeyboardArrowLeftIcon />}
                    sx={{
                        mb: 3,
                    }}
                >
                    {t('file_upload.back_to_file_upload', `Upload file`)}
                </Button>

                <TextField
                    fullWidth
                    type="url"
                    placeholder={t(
                        'file_upload.from_url.placeholder',
                        `Enter file URL`
                    )}
                    value={url}
                    onChange={e => setUrl(e.target.value)}
                    error={Boolean(url && !validateUrl(url))}
                />

                <InputLabel>
                    <Checkbox
                        checked={importFile}
                        onChange={(_e, checked) => setImportFile(checked)}
                    />
                    {t('file_upload.import_file', `Import file`)}
                </InputLabel>
            </>
        );
    }

    return (
        <>
            {!file ? (
                <div>
                    <UploadDropzone onDrop={onDrop} />
                    <Button
                        variant={'text'}
                        onClick={() => setUrlMode(true)}
                        startIcon={<LinkIcon />}
                    >
                        {t('file_upload.upload_from_url', `Upload from URL`)}
                    </Button>
                </div>
            ) : (
                <Box
                    sx={theme => ({
                        bgcolor: theme.palette.grey[100],
                        maxHeight: 400,
                        overflow: 'auto',
                        p: 1,
                    })}
                >
                    <FileToUploadCard
                        file={file}
                        onRemove={() => setFile(undefined)}
                    />
                </Box>
            )}
        </>
    );
}
