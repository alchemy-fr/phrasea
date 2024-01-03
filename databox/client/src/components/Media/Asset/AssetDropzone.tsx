import {PropsWithChildren, useCallback, useEffect} from 'react';
import {useDropzone} from 'react-dropzone';
import UploadModal from '../../Upload/UploadModal';
import {Backdrop, Typography} from '@mui/material';
import {retrieveImageFromClipboardAsBlob} from '../../../lib/ImagePaste';
import {useModals} from '@alchemy/navigation';
import {useAccept} from '../../Upload/UploadDropzone';
import {useAuth} from '@alchemy/react-auth';

export default function AssetDropzone({children}: PropsWithChildren<{}>) {
    const authContext = useAuth();
    const {openModal} = useModals();

    const onDrop = useCallback(
        (acceptedFiles: File[]) => {
            const authenticated = Boolean(authContext.user);
            if (!authenticated) {
                window.alert(
                    'You must be authenticated in order to upload new files'
                );
                return;
            }

            openModal(UploadModal, {
                files: acceptedFiles,
                userId: authContext.user!.id,
            });
        },
        [authContext]
    );

    const onPaste = (e: any) => {
        retrieveImageFromClipboardAsBlob(e, imageBlob => {
            openModal(UploadModal, {
                files: [imageBlob],
                userId: authContext.user!.id,
            });
        });
    };

    useEffect(() => {
        window.addEventListener('paste', onPaste);

        return () => {
            window.removeEventListener('paste', onPaste);
        };
    }, []);

    const accept = useAccept();

    const {getRootProps, getInputProps, isDragActive} = useDropzone({
        noClick: true,
        onDrop,
        noKeyboard: true,
        accept,
    });

    return (
        <div {...getRootProps()}>
            <input {...getInputProps()} />
            {isDragActive && (
                <Backdrop
                    sx={theme => ({
                        zIndex: theme.zIndex.drawer + 1,
                        color: theme.palette.common.white,
                    })}
                    open={true}
                >
                    <Typography typography={'h2'}>
                        Drop the files here ...
                    </Typography>
                </Backdrop>
            )}
            {children}
        </div>
    );
}
