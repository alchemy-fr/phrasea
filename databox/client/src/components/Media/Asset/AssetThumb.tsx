import React, {MouseEvent} from 'react';
import {Asset, File} from "../../../types";
import Thumb from "./Thumb";
import AssetFileIcon from "./AssetFileIcon";
import {FileTypeEnum, getFileTypeFromMIMEType} from "../../../lib/file";
import VideoPlayer from "./Players/VideoPlayer";
import {FileWithUrl} from "./Players";

type Props = {
    selected?: boolean;
    onClick?: (id: string, e: MouseEvent) => void;
    thumbSize: number;
} & Asset;

function FileThumb({
                       file,
                       title,
                       thumbSize,
                   }: {
    file: File;
    title: string | undefined;
    thumbSize: number;
}) {
    const mainType = getFileTypeFromMIMEType(file.type);

    if (!file.url) {
        return <AssetFileIcon file={file}/>
    }

    switch (mainType) {
        case FileTypeEnum.Image:
            return <img src={file.url} alt={title}/>
        case FileTypeEnum.Audio:
        case FileTypeEnum.Video:
            return <VideoPlayer
                file={file as FileWithUrl}
                thumbSize={thumbSize}
            />
        default:
            return <div>
                Unknown TODO
            </div>
    }
}

export default function AssetThumb({

                                       resolvedTitle,
                                       thumbSize,
                                       thumbnail,
                                       thumbnailActive,
                                       original,
                                       selected,
                                   }: Props) {


    return <Thumb
        selected={selected}
        size={thumbSize}
    >
        {thumbnail && <FileThumb
            file={thumbnail}
            title={resolvedTitle}
            thumbSize={thumbSize}
        />}
        {thumbnailActive && <div className={'ta'}>
            <FileThumb
                thumbSize={thumbSize}
                file={thumbnailActive}
                title={resolvedTitle}
            />
        </div>}
        {!thumbnail && original && <AssetFileIcon file={original}/>}
    </Thumb>
}
