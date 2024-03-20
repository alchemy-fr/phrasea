import React, {DOMAttributes} from 'react';
import {Asset} from '../../../types';
import AssetFileIcon from './AssetFileIcon';
import assetClasses from '../Search/Layout/classes';
import FilePlayer from './FilePlayer';
import {CircularProgress, SxProps} from '@mui/material';
import classNames from 'classnames';

type Props = {
    asset: Asset;
} & DOMAttributes<HTMLDivElement>;

function AssetThumb({
    asset: {
        resolvedTitle,
        pendingSourceFile,
        thumbnail,
        thumbnailActive,
        original,
    },
    ...domAttrs
}: Props) {
    return (
        <div
            {...domAttrs}
            className={classNames({
                [assetClasses.thumbWrapper]: true,
            })}
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
                        autoPlayable={false}
                    />
                )}
            </div>
            {!pendingSourceFile && thumbnailActive?.file && (
                <div className={assetClasses.thumbActive}>
                    <FilePlayer
                        file={thumbnailActive.file}
                        title={resolvedTitle}
                        autoPlayable={false}
                    />
                </div>
            )}
        </div>
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

export default React.memo(AssetThumb);
