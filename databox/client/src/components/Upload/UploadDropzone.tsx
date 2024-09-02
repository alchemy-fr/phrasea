import {Accept, DropzoneOptions, useDropzone} from 'react-dropzone';
import {Box, Typography} from '@mui/material';
import {grey} from '@mui/material/colors';
import config from '../../config';
import React from 'react';
import {useTranslation} from 'react-i18next';

export function useAccept(): Accept | undefined {
    return React.useMemo<Accept | undefined>(() => {
        const a = config.allowedTypes;
        if (!a) {
            return;
        }

        const n = {...a};
        try {
            Object.keys(n).forEach(k => {
                n[k] = n[k].map(e => `.${e.replace(/^\./, '')}`);
                if (n[k].length === 0) {
                    throw new Error(
                        `Missing extension list for MIME type ${k}`
                    );
                }
            });
        } catch (e: any) {
            console.error(e.toString());
            return;
        }

        return n;
    }, []);
}

type Props = DropzoneOptions;

export default function UploadDropzone({onDrop, ...rest}: Props) {
    const {t} = useTranslation();
    const accept = useAccept();

    const {getRootProps, getInputProps, isDragActive} = useDropzone({
        ...rest,
        onDrop,
        accept,
        noClick: true,
    });

    return (
        <>
            <Box
                component={'label'}
                sx={theme => ({
                    display: 'block',
                    border: `1px dashed ${grey[500]}`,
                    borderRadius: theme.shape.borderRadius,
                    p: 3,
                    mb: 2,
                    bgcolor: isDragActive ? 'info.main' : undefined,
                    cursor: 'pointer',
                })}
                {...getRootProps()}
            >
                <input {...getInputProps()} />
                <Typography>
                    {t(
                        'upload_dropzone.drag_n_drop_some_files_here_or_click_to_select_files',
                        `Drag 'n' drop some files here, or click to select files`
                    )}
                </Typography>
            </Box>
        </>
    );
}
