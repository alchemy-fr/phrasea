import {DOMAttributes} from 'react';
import {Asset} from '../../../types';
import Thumb from './Thumb';
import AssetFileIcon from './AssetFileIcon';
import assetClasses from '../Search/Layout/classes';
import FilePlayer from './FilePlayer';
import {createDimensions} from './Players';
import {CircularProgress, SxProps} from '@mui/material';

type Props = {
    selected?: boolean;
    thumbSize: number;
    asset: Asset;
} & DOMAttributes<HTMLElement>;

export default function AssetThumb({
    asset: {
        resolvedTitle,
        pendingSourceFile,
        thumbnail,
        thumbnailActive,
        original,
    },
    thumbSize,
    selected,
    ...domAttrs
}: Props) {
    const dimensions = createDimensions(thumbSize);

    return (
        <Thumb
            {...domAttrs}
            selected={selected}
            size={thumbSize}
            className={assetClasses.thumbWrapper}
        >
            <div
                className={
                    thumbnailActive ? assetClasses.thumbInactive : undefined
                }
            >
                {pendingSourceFile && (
                    <CircularProgress title={'Uploading...'} />
                )}
                {!pendingSourceFile && !thumbnail && original?.file && (
                    <AssetFileIcon file={original.file} />
                )}
                {!pendingSourceFile && thumbnail?.file && (
                    <FilePlayer
                        file={thumbnail.file}
                        title={resolvedTitle}
                        minDimensions={dimensions}
                        maxDimensions={dimensions}
                        autoPlayable={false}
                    />
                )}
            </div>
            {!pendingSourceFile && thumbnailActive?.file && (
                <div className={assetClasses.thumbActive}>
                    <FilePlayer
                        minDimensions={dimensions}
                        maxDimensions={dimensions}
                        file={thumbnailActive.file}
                        title={resolvedTitle}
                        autoPlayable={false}
                    />
                </div>
            )}
        </Thumb>
    );
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
    };
}
