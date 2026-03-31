import {ApiFile} from '../../../types';
import {FileTypeEnum, getFileTypeFromMIMEType} from '@alchemy/core';
import AssetFileIcon from './AssetFileIcon';
import VideoPlayer from './Players/VideoPlayer';
import {FileWithUrl, PlayerProps} from './Players';
import PDFPlayer from './Players/PDFPlayer';
import ImagePlayer from './Players/ImagePlayer.tsx';
import React from 'react';
import FileAnalysisChipWrapper from './FileAnalysisChipWrapper.tsx';
import AudioPlayer from './Players/AudioPlayer.tsx';

type Props = {
    file: ApiFile;
    autoPlayable?: boolean;
    autoPlay?: boolean;
} & Omit<PlayerProps, 'file'>;

export default function FilePlayer({
    file,
    autoPlayable,
    autoPlay,
    ...playProps
}: Props) {
    const mainType = getFileTypeFromMIMEType(file.type);

    if (file.analysisPending || false === file.accepted) {
        return (
            <FileAnalysisChipWrapper file={file}>
                <AssetFileIcon mimeType={file.type} />
            </FileAnalysisChipWrapper>
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
                return (
                    <AudioPlayer
                        {...props}
                        autoPlay={autoPlay}
                        autoPlayable={autoPlayable}
                    />
                );
            case FileTypeEnum.Video:
                return (
                    <VideoPlayer
                        {...props}
                        autoPlay={autoPlay}
                        autoPlayable={autoPlayable}
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
