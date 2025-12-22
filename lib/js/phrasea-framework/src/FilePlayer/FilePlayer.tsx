import {FileTypeEnum, getFileTypeFromMIMEType} from '@alchemy/core';
import VideoPlayer from './Players/VideoPlayer';
import PDFPlayer from './Players/PDFPlayer';
import React from 'react';
import AssetTypeIcon from './AssetTypeIcon';
import ImagePlayer from './Players/ImagePlayer';
import {FilePlayerProps} from './types';

type Props = {} & FilePlayerProps;

export default function FilePlayer(props: Props) {
    const {file} = props;
    const mainType = getFileTypeFromMIMEType(file.type);

    if (file.url) {
        switch (mainType) {
            case FileTypeEnum.Image:
                return <ImagePlayer {...props} />;
            case FileTypeEnum.Audio:
            case FileTypeEnum.Video:
                return <VideoPlayer {...props} />;
            case FileTypeEnum.Document:
                if (file.type === 'application/pdf') {
                    return <PDFPlayer {...props} />;
                }
        }
    }

    return <AssetTypeIcon mimeType={file.type} />;
}

export const MemoizedFilePlayer = React.memo(FilePlayer, (prev, next) => {
    return prev.file.id === next.file.id;
});
