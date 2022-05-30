import React, {DOMAttributes} from 'react';
import {Asset} from "../../../types";
import Thumb from "./Thumb";
import AssetFileIcon from "./AssetFileIcon";
import assetClasses from "../Search/Layout/classes";
import FilePlayer from "./FilePlayer";
import {SxProps} from "@mui/system";

type Props = {
    selected?: boolean;
    thumbSize: number;
    asset: Asset;
} & DOMAttributes<HTMLElement>;

export default function AssetThumb({
                                       asset: {
                                           resolvedTitle,
                                           thumbnail,
                                           thumbnailActive,
                                           original,
                                       },
                                       thumbSize,
                                       selected,
                                       ...domAttrs
                                   }: Props) {
    return <Thumb
        {...domAttrs}
        selected={selected}
        size={thumbSize}
    >
        {thumbnail && <FilePlayer
            className={thumbnailActive ? assetClasses.thumbInactive : undefined}
            file={thumbnail}
            title={resolvedTitle}
            size={thumbSize}
            autoPlayable={false}
        />}
        {thumbnailActive && <FilePlayer
            size={thumbSize}
            file={thumbnailActive}
            title={resolvedTitle}
            autoPlayable={false}
            className={assetClasses.thumbActive}
        />}
        {!thumbnail && original && <AssetFileIcon file={original}/>}
    </Thumb>
}

export function createThumbActiveStyle(): SxProps {
    return {
        [`.${assetClasses.thumbActive}`]: {
            display: 'none',
        },
        '&:hover': {
            [`.${assetClasses.thumbActive}`]: {
                display: 'block',
            },
            [`.${assetClasses.thumbInactive}`]: {
                display: 'none',
            },
        },
    }
}
