import React, {PropsWithChildren, useCallback, useContext, useState} from "react";
import {useDropzone} from "react-dropzone";
import {UserContext} from "../../Security/UserContext";
import UploadModal from "../../Upload/UploadModal";
import {Backdrop, Typography} from "@mui/material";

export default function AssetDropzone({children}: PropsWithChildren<{}>) {
    const userContext = useContext(UserContext);

    const [files, setFiles] = useState<File[] | undefined>();
    const onDrop = useCallback((acceptedFiles: File[]) => {
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
        {isDragActive && <Backdrop
            sx={(theme) => ({
                zIndex: theme.zIndex.drawer + 1,
                color: '#fff',
            })}
            open={true}
        >
            <Typography typography={'h2'} >Drop the files here ...</Typography>
        </Backdrop>}
        {children}
    </div>
}
