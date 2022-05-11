import React from 'react';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import {FileBlobThumb} from "../../lib/upload/fileBlob";
import {Grid, Paper} from "@mui/material";
import byteSize from 'byte-size';

const size = 128;

type Props = {
    file: File,
}

export default function FileCard({file}: Props) {
    return <Paper sx={(theme) => ({
        padding: theme.spacing(2),
        margin: 'auto',
    })}>
        <Grid container spacing={2}>
            <Grid item>
                <FileBlobThumb
                    file={file}
                    width={size}
                    height={size}
                />
            </Grid>
            <Grid item xs={12} sm container>
                <Grid item xs container direction="column" spacing={2}>
                    <Grid item xs>
                        <Typography gutterBottom variant="subtitle1">
                            {file.name}
                        </Typography>
                        <Typography variant="body2" gutterBottom>
                            {byteSize(file.size).toString()} • {file.type}
                        </Typography>
                    </Grid>
                    <Grid item>
                        <Button size="small" color="secondary">
                            Remove
                        </Button>
                    </Grid>
                </Grid>
            </Grid>
        </Grid>
    </Paper>
}
