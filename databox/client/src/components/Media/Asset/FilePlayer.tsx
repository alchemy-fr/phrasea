import React from 'react';
import {File} from "../../../types";
import {FileTypeEnum, getFileTypeFromMIMEType} from "../../../lib/file";
import AssetFileIcon from "./AssetFileIcon";
import VideoPlayer from "./Players/VideoPlayer";
import {Dimensions, FileWithUrl} from "./Players";
import PDFPlayer from "./Players/PDFPlayer";

type Props = {
    file: File;
    title: string | undefined;
    minDimensions?: Dimensions;
    maxDimensions: Dimensions;
    onLoad?: () => void;
    noInteraction?: boolean;
    autoPlayable: boolean;
};

export default function FilePlayer({
                                       file,
                                       title,
                                       minDimensions,
                                       maxDimensions,
                                       onLoad,
                                       noInteraction,
                                       autoPlayable,
                                   }: Props) {
    const mainType = getFileTypeFromMIMEType(file.type);

    if (!file.url) {
        return <AssetFileIcon
            file={file}
        />
    }

    switch (mainType) {
        case FileTypeEnum.Image:
            return <img
                    style={{
                        maxWidth: maxDimensions.width,
                        maxHeight: maxDimensions.height,
                        display: 'block',
                    }}
                    src={file.url}
                    alt={title}
                    onLoad={onLoad}
                />
        case FileTypeEnum.Audio:
        case FileTypeEnum.Video:
            return <VideoPlayer
                    file={file as FileWithUrl}
                    minDimensions={minDimensions}
                    maxDimensions={maxDimensions}
                    onLoad={onLoad}
                    noInteraction={noInteraction}
                    autoPlayable={autoPlayable}
                />
        case FileTypeEnum.Document:
            return <PDFPlayer
                    file={file as FileWithUrl}
                    minDimensions={minDimensions}
                    maxDimensions={maxDimensions}
                    onLoad={onLoad}
                    noInteraction={noInteraction}
                />
        default:
            return <div
                style={{
                    width: '100%',
                    height: '100%',
                }}
            >
                Unknown TODO
            </div>
    }
}
