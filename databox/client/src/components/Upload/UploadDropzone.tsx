import React from 'react';
import Dropzone, {DropzoneOptions} from "react-dropzone";
import {Box, Typography} from "@mui/material";
import {grey} from "@mui/material/colors";

type Props = {
    onDrop: DropzoneOptions['onDrop'];
};

export default function UploadDropzone({
    onDrop
}: Props) {

    return <Dropzone
        onDrop={onDrop}
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
