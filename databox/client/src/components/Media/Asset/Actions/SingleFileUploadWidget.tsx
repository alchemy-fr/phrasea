import UploadDropzone from '../../../Upload/UploadDropzone.tsx';
import {Box, Button, TextField} from '@mui/material';
import React from 'react';
import {useTranslation} from 'react-i18next';
import LinkIcon from '@mui/icons-material/Link';
import {validateUrl} from '../../../../lib/file.ts';
import FileToUploadCard from '../../../Upload/FileToUploadCard.tsx';
import {FileOrUrl} from '../../../../api/file.ts';

export type AssetUploadForm = FileOrUrl;

type Props = {
    onUpload?: (upload: AssetUploadForm | undefined) => void;
};

export default function SingleFileUploadWidget({onUpload}: Props) {
    const [file, setFileProxy] = React.useState<File | undefined>();
    const [urlMode, setUrlMode] = React.useState(false);
    const [url, setUrlProxy] = React.useState<string | undefined>();
    const {t} = useTranslation();

    const setFile = (file: File | undefined) => {
        setFileProxy(file);
        if (file) {
            onUpload?.({file} as AssetUploadForm);
        } else {
            onUpload?.(undefined);
        }
    };

    const setUrl = (url: string) => {
        setUrlProxy(url);
        setFileProxy(undefined);
        if (url && validateUrl(url)) {
            onUpload?.({url});
        } else {
            onUpload?.(undefined);
        }
    };

    const onDrop = (acceptedFiles: File[]) => {
        setFile(acceptedFiles[0]);
    };

    if (urlMode) {
        return (
            <>
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
