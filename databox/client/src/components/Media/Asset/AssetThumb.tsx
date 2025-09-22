import React, {HTMLAttributes, ReactNode} from 'react';
import {Asset} from '../../../types';
import AssetFileIcon from './AssetFileIcon';
import assetClasses from '../../AssetList/classes';
import FilePlayer from './FilePlayer';
import {Chip, Skeleton, SxProps} from '@mui/material';
import classNames from 'classnames';
import {alpha, Theme} from '@mui/material/styles';
import {videoPlayerSx} from './Players/VideoPlayer.tsx';
import StoryThumb, {createStorySx} from './StoryThumb.tsx';
import BurstModeIcon from '@mui/icons-material/BurstMode';
import {useTranslation} from 'react-i18next';
import AssetTypeIcon from './AssetTypeIcon.tsx';

type Props = {
    asset: Asset;
} & HTMLAttributes<HTMLDivElement>;

function AssetThumb({
    asset: {
        id,
        resolvedTitle,
        pendingSourceFile,
        thumbnail,
        thumbnailActive,
        original,
        storyCollection,
    },
    ...domAttrs
}: Props) {
    const {t} = useTranslation();
    let thumb: ReactNode | undefined;
    const assetFileIcon = original?.file ? (
        <AssetFileIcon mimeType={original.file.type} />
    ) : undefined;

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
        thumb = assetFileIcon;
    }

    const displayAssetTypeChip =
        thumb && assetFileIcon && assetFileIcon !== thumb;

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
            {storyCollection ? <StoryThumb assetId={id} /> : null}
            {thumb ? (
                <div
                    className={classNames({
                        [assetClasses.thumbInactive]: thumbnailActive,
                        [assetClasses.storyShouldHide]: !!storyCollection,
                    })}
                >
                    {thumb}
                </div>
            ) : (
                ''
            )}
            {!pendingSourceFile &&
                thumbnailActive?.file &&
                !storyCollection && (
                    <div className={assetClasses.thumbActive}>
                        <FilePlayer
                            file={thumbnailActive.file}
                            title={resolvedTitle}
                            autoPlayable={false}
                        />
                    </div>
                )}

            {storyCollection || displayAssetTypeChip ? (
                <div className={assetClasses.assetChip}>
                    {storyCollection ? (
                        <Chip
                            color={'info'}
                            icon={<BurstModeIcon />}
                            label={t('story.chip.label', 'Story')}
                        />
                    ) : (
                        <Chip
                            color={'info'}
                            icon={
                                <AssetTypeIcon
                                    mimeType={original!.file!.type}
                                />
                            }
                        />
                    )}
                </div>
            ) : null}
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
) => {
    const greyBg = theme.palette.grey[100];

    return {
        ...createStorySx(thumbSize, theme),
        [`.${assetClasses.thumbWrapper}`]: {
            'display': 'flex',
            'overflow': 'hidden',
            'alignItems': 'center',
            'position': 'relative',
            'justifyContent': 'center',
            'backgroundColor': greyBg,
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
            [`.${assetClasses.assetChip}`]: {
                'display': 'block',
                'position': 'absolute',
                'zIndex': 2,
                'right': theme.spacing(1),
                'bottom': theme.spacing(1),
                '.MuiChip-label:empty': {
                    paddingLeft: 0,
                },
            },

            ...createThumbActiveStyle(),
            ...videoPlayerSx(thumbSize, theme),
            ...overridden,
        },
        [`.${assetClasses.item}.selected .${assetClasses.thumbWrapper}:after`]:
            {
                position: 'absolute',
                content: '""',
                top: 0,
                left: 0,
                bottom: 0,
                right: 0,
                backgroundColor: alpha(theme.palette.primary.main, 0.3),
                zIndex: 1,
                pointerEvents: 'none',
            },
    };
};
