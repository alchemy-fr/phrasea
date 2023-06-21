import React from 'react';
import Dropzone, {Accept, DropzoneOptions} from "react-dropzone";
import {Box, Typography} from "@mui/material";
import {grey} from "@mui/material/colors";
import config from "../../config";

export function useAccept() {
    return React.useMemo<Accept | undefined>(() => {
        const list = [
            ...(config.get('allowedTypes') as string[]),
            ...(config.get('allowedExtensions') as string[]).map(e => `.${e}`),
        ];

        return list.length > 0 ? {'image/*': list} : undefined;
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
