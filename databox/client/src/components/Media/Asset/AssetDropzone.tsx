import {PropsWithChildren, useCallback, useEffect} from 'react';
import {useDropzone} from 'react-dropzone';
import UploadDialog from '../../Upload/UploadDialog.tsx';
import {Backdrop, Typography} from '@mui/material';
import {retrieveImageFromClipboardAsBlob} from '../../../lib/ImagePaste.ts';
import {useModals} from '@alchemy/navigation';
import {useAccept} from '../../Upload/UploadDropzone';
import {useAuth} from '@alchemy/react-auth';
import {useTranslation} from 'react-i18next';

export default function AssetDropzone({children}: PropsWithChildren<{}>) {
    const {t} = useTranslation();
    const authContext = useAuth();
    const {openModal} = useModals();

    const onDrop = useCallback(
        (acceptedFiles: File[]) => {
            const authenticated = Boolean(authContext.user);
            if (!authenticated) {
                window.alert(
                    t(
                        'asset_dropzone.you_must_be_authenticated_in_order_to_upload_new_files',
                        `You must be authenticated in order to upload new files`
                    )
                );
                return;
            }

            openModal(UploadDialog, {
                files: acceptedFiles,
            });
        },
        [authContext]
    );

    const onPaste = (e: any) => {
        retrieveImageFromClipboardAsBlob(e, imageBlob => {
            openModal(UploadDialog, {
                files: [imageBlob],
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
                        {t(
                            'asset_dropzone.drop_the_files_here',
                            `Drop the files here…`
                        )}
                    </Typography>
                </Backdrop>
            )}
            {children}
        </div>
    );
}
