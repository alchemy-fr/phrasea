import React, {PropsWithChildren, useCallback, useContext, useState} from "react";
import {useDropzone} from "react-dropzone";
import {UserContext} from "../../Security/UserContext";
import UploadModal from "../../Upload/UploadModal";
import {Backdrop, Theme, Typography} from "@mui/material";
import {createStyles, makeStyles} from "@material-ui/core/styles";

const useStyles = makeStyles((theme: Theme) =>
    createStyles({
        backdrop: {
            zIndex: theme.zIndex.drawer + 1,
            color: '#fff',
        },
    }),
);

export default function AssetDropzone({children}: PropsWithChildren<{}>) {
    const userContext = useContext(UserContext);

    const classes = useStyles();

    const [files, setFiles] = useState<File[] | undefined>();
    const onDrop = useCallback(acceptedFiles => {
        const authenticated = Boolean(userContext.user);
        if (!authenticated) {
            window.alert('You must be authenticated in order to upload new files');
            return;
        }
        setFiles(acceptedFiles);
    }, [userContext]);

    const closeUpload = () => {
        setFiles(undefined)
    }

    const {getRootProps, getInputProps, isDragActive} = useDropzone({
        noClick: true,
        onDrop
    });

    return <div {...getRootProps()}>
        {files ? <UploadModal
            files={files}
            userId={userContext.user!.id}
            onClose={closeUpload}
        /> : ''}
        <input {...getInputProps()} />
        {isDragActive && <Backdrop className={classes.backdrop} open={true}>
            <Typography typography={'h2'} >Drop the files here ...</Typography>
        </Backdrop>}
        {children}
    </div>
}
