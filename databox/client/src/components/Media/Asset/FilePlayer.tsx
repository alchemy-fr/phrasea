import {ApiFile} from '../../../types';
import {FileTypeEnum, getFileTypeFromMIMEType} from '../../../lib/file';
import AssetFileIcon from './AssetFileIcon';
import VideoPlayer from './Players/VideoPlayer';
import {FileWithUrl, PlayerProps} from './Players';
import PDFPlayer from './Players/PDFPlayer';
import ImagePlayer from './Players/ImagePlayer.tsx';
import React from 'react';
import HourglassBottomIcon from '@mui/icons-material/HourglassBottom';
import {Chip} from '@mui/material';
import {useTranslation} from 'react-i18next';

type Props = {
    file: ApiFile;
    autoPlayable?: boolean;
} & Omit<PlayerProps, 'file'>;

export default function FilePlayer({file, autoPlayable, ...playProps}: Props) {
    const mainType = getFileTypeFromMIMEType(file.type);
    const {t} = useTranslation();

    if (file.analysisPending) {
        return (
            <div
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    flexDirection: 'column',
                    gap: '8px',
                }}
            >
                <Chip
                    label={t('file.analysis_pending', 'Analysis in progress…')}
                    color="info"
                    size="small"
                    icon={<HourglassBottomIcon />}
                />
                <AssetFileIcon mimeType={file.type} />
            </div>
        );
    }

    if (file.url) {
        const props: PlayerProps = {
            ...playProps,
            file: file as FileWithUrl,
        };

        switch (mainType) {
            case FileTypeEnum.Image:
                return <ImagePlayer {...props} />;
            case FileTypeEnum.Audio:
            case FileTypeEnum.Video:
                return (
                    <VideoPlayer
                        {...props}
                        autoPlayable={autoPlayable || false}
                    />
                );
            case FileTypeEnum.Document:
                if (file.type === 'application/pdf') {
                    return <PDFPlayer {...props} />;
                }
        }
    }

    return <AssetFileIcon mimeType={file.type} />;
}

export const MemoizedFilePlayer = React.memo(FilePlayer, (prev, next) => {
    return prev.file.id === next.file.id;
});
