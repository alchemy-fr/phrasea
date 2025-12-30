import UploadBatch from '../../uploadBatch.ts';
import React from 'react';
import {Box, Button, Container, LinearProgress} from '@mui/material';
import FileList from './FileList.tsx';
import FileCard from './FileCard.tsx';
import {useTranslation} from 'react-i18next';

type Props = {
    files: File[];
    onNext: () => void;
    onCancel?: () => void;
    uploadBatch: UploadBatch;
};

export default function UploadProgress({
    uploadBatch,
    files,
    onNext,
    onCancel,
}: Props) {
    const {t} = useTranslation();
    const [progress, setProgress] = React.useState(0);

    React.useEffect(() => {
        uploadBatch.registerProgressHandler(e => {
            setProgress(e.totalPercent);
        });
        uploadBatch.registerFileCompleteHandler(e => {
            setProgress(e.totalPercent);
        });
        uploadBatch.registerCompleteHandler(() => {
            setProgress(100);
            uploadBatch.commit();
            onNext();
        });

        return () => {
            uploadBatch.resetListeners();
        };
    }, [onNext, uploadBatch]);

    return (
        <Container>
            <Box
                sx={{
                    mt: 2,
                }}
            >
                <FileList>
                    {files.map((file, index) => {
                        return (
                            <FileCard
                                key={index}
                                file={file}
                                uploadProgress={uploadBatch.getFileProgress(
                                    file
                                )}
                            />
                        );
                    })}
                </FileList>
            </Box>
            <Box
                sx={{
                    mt: 2,
                }}
            >
                <LinearProgress variant={'determinate'} value={progress} />
            </Box>
            <Box
                sx={{
                    mt: 2,
                    textAlign: 'center',
                }}
            >
                {onCancel ? (
                    <div>
                        <Button variant={'outlined'} onClick={onCancel}>
                            {t('common.cancel', 'Cancel')}
                        </Button>
                    </div>
                ) : null}
            </Box>
        </Container>
    );
}
