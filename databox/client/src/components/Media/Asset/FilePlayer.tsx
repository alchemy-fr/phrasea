import React from 'react';
import {File} from "../../../types";
import {FileTypeEnum, getFileTypeFromMIMEType} from "../../../lib/file";
import AssetFileIcon from "./AssetFileIcon";
import VideoPlayer from "./Players/VideoPlayer";
import {FileWithUrl} from "./Players";
import PDFPlayer from "./Players/PDFPlayer";

type Props = {
    file: File;
    title: string | undefined;
    size: number | string;
    className?: string | undefined;
    onLoad?: () => void;
    noInteraction?: boolean;
};

export default function FilePlayer({
                                       file,
                                       title,
                                       size,
                                       className,
                                       onLoad,
                                       noInteraction,
                                   }: Props) {
    const mainType = getFileTypeFromMIMEType(file.type);

    if (!file.url) {
        return <AssetFileIcon
            file={file}
            className={className}
        />
    }

    switch (mainType) {
        case FileTypeEnum.Image:
            return <img
                src={file.url}
                className={className}
                alt={title}
                onLoad={onLoad}
            />
        case FileTypeEnum.Audio:
        case FileTypeEnum.Video:
            return <div
                className={className}
            >
                <VideoPlayer
                    file={file as FileWithUrl}
                    thumbSize={size}
                    onLoad={onLoad}
                    noInteraction={noInteraction}
                />
            </div>
        case FileTypeEnum.Document:
            return <div>
                <PDFPlayer
                    file={file as FileWithUrl}
                    thumbSize={size}
                    onLoad={onLoad}
                    noInteraction={noInteraction}
                />
            </div>
        default:
            return <div
                className={className}
                style={{
                    width: '100%',
                    height: '100%',
                }}
            >
                Unknown TODO
            </div>
    }
}
