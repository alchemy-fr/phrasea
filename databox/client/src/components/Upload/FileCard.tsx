import React from 'react';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import {FileBlobThumb} from "../../lib/upload/fileBlob";
import {Grid, Paper} from "@mui/material";
import byteSize from 'byte-size';

const size = 100;

type Props = {
    file: File;
    onRemove: () => void;
}

export default function FileCard({
                                     file,
                                     onRemove,
                                 }: Props) {
    return <Paper sx={(theme) => ({
        padding: theme.spacing(2),
        margin: 'auto',
    })}>
        <Grid
            sx={(theme) => ({
                width: {
                    xs: `calc(${size}px + ${theme.spacing(2)})`,
                    sm: 395,
                },
            })}
            container spacing={2}>
            <Grid item>
                <FileBlobThumb
                    file={file}
                    size={size}
                />
            </Grid>
            <Grid item xs={12} sm>
                <Typography
                    sx={{
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        display: '-webkit-box',
                        WebkitLineClamp: '2',
                        WebkitBoxOrient: 'vertical',
                        lineHeight: 1.2
                    }}
                    gutterBottom variant="subtitle1">
                    {file.name}
                </Typography>
                <Typography variant="body2" gutterBottom>
                    {byteSize(file.size).toString()} • {file.type}
                </Typography>
                <Button
                    size="small"
                    color="error"
                    onClick={onRemove}
                >
                    Remove
                </Button>
            </Grid>
        </Grid>
    </Paper>
}
