import React from 'react';
import Dropzone, {Accept, DropzoneOptions} from "react-dropzone";
import {Box, Typography} from "@mui/material";
import {grey} from "@mui/material/colors";
import config from "../../config";

export function useAccept(): Accept | undefined {
    return React.useMemo<Accept | undefined>(() => {
        const a = config.get('allowedTypes') as Accept | undefined;
        if (!a) {
            return;
        }

        const n = {...a};
        try {
            Object.keys(n).forEach(k => {
                n[k] = n[k].map(e => `.${e.replace(/^\./, '')}`);
                if (n[k].length === 0) {
                    throw new Error(`Missing extension list for MIME type ${k}`);
                }
            });
        } catch (e: any) {
            console.error(e.toString());
            return;
        }

        return n;
    }, []);
}

type Props = {
    onDrop: DropzoneOptions['onDrop'];
};

export default function UploadDropzone({
    onDrop,
}: Props) {
    const accept = useAccept();

    return <Dropzone
        onDrop={onDrop}
        accept={accept}
    >
        {({getRootProps, getInputProps, isDragActive}) => (
            <Box
                sx={theme => ({
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
                <Typography>Drag 'n' drop some files here, or click to select files</Typography>
            </Box>
        )}
    </Dropzone>
}
