import {useUploadStore} from '../../store/uploadStore.ts';
import {Box, Button} from '@mui/material';
import {StackedModalProps, useModals} from '@alchemy/navigation';
import {AppDialog} from '@alchemy/phrasea-ui';
import {useTranslation} from 'react-i18next';
import {Dimensions} from '../Media/Asset/Players';
import React from 'react';
import FileProgressCard from './FileProgressCard.tsx';

type Props = {} & StackedModalProps;

export default function PendingUploadsDialog({open, modalIndex}: Props) {
    const uploads = useUploadStore(state => state.uploads);
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const dimensions: Dimensions = {
        width: 200,
        height: 200,
    };

    const onCancelUpload = useUploadStore(state => state.removeUpload);

    return (
        <AppDialog
            modalIndex={modalIndex}
            open={open}
            title={t('upload.pending.title', 'Pending Uploads')}
            onClose={closeModal}
            actions={({onClose}) => (
                <>
                    <Button onClick={onClose}>
                        {t('dialog.close', 'Close')}
                    </Button>
                </>
            )}
        >
            {uploads.length === 0 ? (
                <Box p={2}>
                    {t('upload.pending.empty', 'No pending uploads.')}
                </Box>
            ) : (
                <Box
                    sx={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 2,
                        justifyContent: 'space-between',
                        alignItems: 'stretch',
                    }}
                >
                    {uploads.map(upload => (
                        <div
                            key={upload.id}
                            style={{
                                width: '100%',
                            }}
                        >
                            <FileProgressCard
                                file={upload.file}
                                size={dimensions.width}
                                progress={upload.progress}
                                onCancel={() => onCancelUpload(upload.id)}
                            />
                        </div>
                    ))}
                </Box>
            )}
        </AppDialog>
    );
}
