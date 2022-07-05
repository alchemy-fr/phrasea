import React, {PropsWithChildren, useCallback, useContext} from "react";
import {useDropzone} from "react-dropzone";
import {UserContext} from "../../Security/UserContext";
import UploadModal from "../../Upload/UploadModal";
import {Backdrop, Typography} from "@mui/material";
import {useModalHash} from "../../../hooks/useModalHash";

export default function AssetDropzone({children}: PropsWithChildren<{}>) {
    const userContext = useContext(UserContext);
    const {openModal} = useModalHash();

    const onDrop = useCallback((acceptedFiles: File[]) => {
        const authenticated = Boolean(userContext.user);
        if (!authenticated) {
            window.alert('You must be authenticated in order to upload new files');
            return;
        }

        openModal(UploadModal, {
            files: acceptedFiles,
            userId: userContext.user!.id,
        });
    }, [userContext]);

    const {getRootProps, getInputProps, isDragActive} = useDropzone({
        noClick: true,
        onDrop,
        noKeyboard: true,
    });

    return <div {...getRootProps()}>
        <input {...getInputProps()} />
        {isDragActive && <Backdrop
            sx={(theme) => ({
                zIndex: theme.zIndex.drawer + 1,
                color: theme.palette.common.white,
            })}
            open={true}
        >
            <Typography typography={'h2'}>Drop the files here ...</Typography>
        </Backdrop>}
        {children}
    </div>
}
