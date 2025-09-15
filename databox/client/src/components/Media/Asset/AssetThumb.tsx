import React, {HTMLAttributes, ReactNode} from 'react';
import {Asset} from '../../../types';
import AssetFileIcon from './AssetFileIcon';
import assetClasses from '../../AssetList/classes';
import FilePlayer from './FilePlayer';
import {Skeleton, SxProps} from '@mui/material';
import classNames from 'classnames';
import {alpha, Theme} from '@mui/material/styles';
import {videoPlayerSx} from './Players/VideoPlayer.tsx';
import StoryChip from '../../AssetList/Layouts/StoryChip.tsx';

type Props = {
    asset: Asset;
} & HTMLAttributes<HTMLDivElement>;

function AssetThumb({
    asset: {
        resolvedTitle,
        pendingSourceFile,
        thumbnail,
        thumbnailActive,
        original,
        storyCollection,
    },
    ...domAttrs
}: Props) {
    let thumb: ReactNode | undefined;

    if (pendingSourceFile) {
        thumb = (
            <Skeleton
                style={{
                    borderRadius: 0,
                    transform: 'none',
                    width: '100%',
                    height: '100%',
                }}
            />
        );
    } else if (thumbnail?.file) {
        thumb = (
            <FilePlayer
                file={thumbnail.file}
                title={resolvedTitle}
                autoPlayable={false}
            />
        );
    } else if (original?.file) {
        thumb = <AssetFileIcon mimeType={original.file.type} />;
    }

    return (
        <div
            {...domAttrs}
            className={classNames(
                {
                    [assetClasses.thumbWrapper]: true,
                },
                [domAttrs.className]
            )}
        >
            {thumb ? (
                <div
                    className={
                        thumbnailActive ? assetClasses.thumbInactive : undefined
                    }
                >
                    {thumb}
                </div>
            ) : (
                ''
            )}
            {!pendingSourceFile && thumbnailActive?.file && (
                <div className={assetClasses.thumbActive}>
                    <FilePlayer
                        file={thumbnailActive.file}
                        title={resolvedTitle}
                        autoPlayable={false}
                    />
                </div>
            )}
            {storyCollection ? <StoryChip /> : null}
        </div>
    );
}

export default React.memo(AssetThumb);

export function createThumbActiveStyle(): SxProps {
    return {
        [`.${assetClasses.thumbActive}`]: {
            display: 'none',
        },
        '&:hover': {
            [`.${assetClasses.thumbActive}`]: {
                display: 'contents',
            },
            [`.${assetClasses.thumbInactive}`]: {
                display: 'none',
            },
        },
    };
}

export function createSizeTransition(theme: Theme) {
    return theme.transitions.create(['height', 'width'], {duration: 300});
}

export const thumbSx = (
    thumbSize: number,
    theme: Theme,
    overridden: SxProps = {}
) => ({
    [`.${assetClasses.thumbWrapper}`]: {
        'display': 'flex',
        'overflow': 'hidden',
        'alignItems': 'center',
        'position': 'relative',
        'justifyContent': 'center',
        'backgroundColor': theme.palette.grey[100],
        'img': {
            maxWidth: '100%',
            maxHeight: '100%',
        },
        'width': thumbSize,
        'height': thumbSize,
        'transition': createSizeTransition(theme),
        '> div': {
            display: 'contents',
        },
        [`.${assetClasses.storyChip}`]: {
            position: 'absolute',
            width: '100%',
            bottom: theme.spacing(1),
            textAlign: 'center',
            left: 0,
            zIndex: 2,
            display: 'inline-block',
        },

        ...createThumbActiveStyle(),
        ...videoPlayerSx(thumbSize, theme),
        ...overridden,
    },
    [`.${assetClasses.item}.selected .${assetClasses.thumbWrapper}:after`]: {
        position: 'absolute',
        content: '""',
        top: 0,
        left: 0,
        bottom: 0,
        right: 0,
        backgroundColor: alpha(theme.palette.primary.main, 0.3),
        zIndex: 1,
    },
});
