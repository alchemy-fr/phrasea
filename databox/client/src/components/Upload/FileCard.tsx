import React from 'react';
import { makeStyles } from '@material-ui/core/styles';
import Button from '@material-ui/core/Button';
import Typography from '@material-ui/core/Typography';
import {FileBlobThumb} from "../../lib/upload/fileBlob";
import {Grid, Paper} from "@material-ui/core";
import byteSize from 'byte-size';

const size = 128;

const useStyles = makeStyles((theme) => ({
    paper: {
        padding: theme.spacing(2),
        margin: 'auto',
    },
    image: {
        width: size,
        height: size,
    },
}));

type Props = {
    file: File,
}

export default function FileCard({file}: Props) {
    const classes = useStyles();

    return <Paper className={classes.paper}>
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
