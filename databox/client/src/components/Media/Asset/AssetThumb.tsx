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
        className={assetClasses.thumbWrapper}
    >
        <div
            className={thumbnailActive ? assetClasses.thumbInactive : undefined}
        >
            {!thumbnail && original?.file && <AssetFileIcon file={original.file}/>}
            {thumbnail?.file && <FilePlayer
                file={thumbnail.file}
                title={resolvedTitle}
                minDimensions={dimensions}
                maxDimensions={dimensions}
                autoPlayable={false}
            />}
        </div>
        {thumbnailActive?.file && <div className={assetClasses.thumbActive}>
            <FilePlayer
                minDimensions={dimensions}
                maxDimensions={dimensions}
                file={thumbnailActive.file}
                title={resolvedTitle}
                autoPlayable={false}
            />
        </div>}
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
