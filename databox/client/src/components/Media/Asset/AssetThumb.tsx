import React, {DOMAttributes} from 'react';
import {Asset} from "../../../types";
import Thumb from "./Thumb";
import AssetFileIcon from "./AssetFileIcon";
import assetClasses from "../Search/Layout/classes";
import FilePlayer from "./FilePlayer";
import {SxProps} from "@mui/system";
import {createDimensions} from "./Players";

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
    const dimensions = createDimensions(thumbSize);

    return <Thumb
        {...domAttrs}
        selected={selected}
        size={thumbSize}
    >
        {thumbnail && <div
            className={thumbnailActive ? assetClasses.thumbInactive : undefined}
        >
            <FilePlayer
                file={thumbnail}
                title={resolvedTitle}
                minDimensions={dimensions}
                maxDimensions={dimensions}
                autoPlayable={false}
            />
        </div>}
        {thumbnailActive && <div className={assetClasses.thumbActive}>
            <FilePlayer
                minDimensions={dimensions}
                maxDimensions={dimensions}
                file={thumbnailActive}
                title={resolvedTitle}
                autoPlayable={false}
            />
        </div>}
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
