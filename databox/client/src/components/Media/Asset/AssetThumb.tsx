import React, {MouseEvent} from 'react';
import {Asset, File} from "../../../types";
import Thumb from "./Thumb";
import AssetFileIcon from "./AssetFileIcon";
import {FileTypeEnum, getFileTypeFromMIMEType} from "../../../lib/file";
import VideoPlayer from "./Players/VideoPlayer";
import {FileWithUrl} from "./Players";
import assetClasses from "../Search/Layout/classes";

type Props = {
    selected?: boolean;
    onClick?: (id: string, e: MouseEvent) => void;
    thumbSize: number;
} & Asset;

function FileThumb({
                       file,
                       title,
                       thumbSize,
                       className,
                   }: {
    file: File;
    title: string | undefined;
    thumbSize: number;
    className: string | undefined;
}) {
    const mainType = getFileTypeFromMIMEType(file.type);

    if (!file.url) {
        return <AssetFileIcon file={file}/>
    }

    switch (mainType) {
        case FileTypeEnum.Image:
            return <img
                src={file.url}
                className={className}
                alt={title}
            />
        case FileTypeEnum.Audio:
        case FileTypeEnum.Video:
            return <div
                className={className}
            >
                <VideoPlayer
                    file={file as FileWithUrl}
                    thumbSize={thumbSize}
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
            className={thumbnailActive ? assetClasses.thumbInactive : undefined}
            file={thumbnail}
            title={resolvedTitle}
            thumbSize={thumbSize}
        />}
        {thumbnailActive && <FileThumb
            thumbSize={thumbSize}
            file={thumbnailActive}
            title={resolvedTitle}
            className={assetClasses.thumbActive}
        />}
        {!thumbnail && original && <AssetFileIcon file={original}/>}
    </Thumb>
}
