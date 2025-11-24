import {ApiFile} from '../../../types';
import {FileTypeEnum, getFileTypeFromMIMEType} from '../../../lib/file';
import AssetFileIcon from './AssetFileIcon';
import VideoPlayer from './Players/VideoPlayer';
import {FileWithUrl, PlayerProps} from './Players';
import PDFPlayer from './Players/PDFPlayer';
import ImagePlayer from './Players/ImagePlayer.tsx';
import React from 'react';
import FileAnalysisChipWrapper from './FileAnalysisChipWrapper.tsx';

type Props = {
    file: ApiFile;
    trackingId?: string;
    autoPlayable?: boolean;
} & Omit<PlayerProps, 'file'>;

export default function FilePlayer({
    file,
    trackingId,
    autoPlayable,
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
                return <ImagePlayer {...props} trackingId={trackingId} />;
            case FileTypeEnum.Audio:
            case FileTypeEnum.Video:
                return (
                    <VideoPlayer
                        {...props}
                        autoPlayable={autoPlayable || false}
                        trackingId={trackingId}
                    />
                );
            case FileTypeEnum.Document:
                if (file.type === 'application/pdf') {
                    return <PDFPlayer {...props} trackingId={trackingId} />;
                }
        }
    }

    return <AssetFileIcon mimeType={file.type} />;
}

export const MemoizedFilePlayer = React.memo(FilePlayer, (prev, next) => {
    return prev.file.id === next.file.id;
});
