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
import {useTranslation} from 'react-i18next';
import AssetTypeIcon from './AssetTypeIcon.tsx';
import LayersIcon from '@mui/icons-material/Layers';
import {OnPreviewToggle} from '../../AssetList/types.ts';

type Props = {
    asset: Asset;
    noStoryCarousel?: boolean;
    onPreviewToggle?: OnPreviewToggle;
} & HTMLAttributes<HTMLDivElement>;

function AssetThumb({
    asset,
    noStoryCarousel,
    onPreviewToggle,
    ...domAttrs
}: Props) {
    const {t} = useTranslation();
    const {
        id,
        resolvedTitle,
        pendingSourceFile,
        thumbnail,
        animatedThumbnail,
        main,
        storyCollection,
    } = asset;

    let thumb: ReactNode | undefined;
    const assetFileIcon = main?.file ? (
        <AssetFileIcon mimeType={main.file.type} />
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
    } else if (main?.file) {
        thumb = assetFileIcon;
    }

    const displayAssetTypeChip = Boolean(thumb && assetFileIcon);

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
            {!noStoryCarousel && storyCollection ? (
                <StoryThumb assetId={id} />
            ) : null}
            {thumb || storyCollection ? (
                <div
                    className={classNames({
                        [assetClasses.thumbInactive]: animatedThumbnail,
                        [assetClasses.storyShouldHide]:
                            !noStoryCarousel && !!storyCollection,
                    })}
                >
                    {thumb || (
                        <div className={assetClasses.assetIcon}>
                            <LayersIcon />
                        </div>
                    )}
                </div>
            ) : (
                ''
            )}
            {!pendingSourceFile &&
                animatedThumbnail?.file &&
                (!storyCollection || noStoryCarousel) && (
                    <div className={assetClasses.animatedThumb}>
                        <FilePlayer
                            file={animatedThumbnail.file}
                            title={resolvedTitle}
                            autoPlayable={true}
                            controls={false}
                            noInteraction={true}
                        />
                    </div>
                )}

            {storyCollection || displayAssetTypeChip ? (
                <div className={assetClasses.assetChip}>
                    {storyCollection ? (
                        <Chip
                            color={'info'}
                            icon={<LayersIcon />}
                            label={t('story.chip.label', 'Story')}
                        />
                    ) : (
                        <Chip
                            onMouseOver={e =>
                                onPreviewToggle?.({
                                    asset,
                                    anchorEl: (e.target as HTMLElement).closest(
                                        `.${assetClasses.thumbWrapper}`
                                    ) as HTMLElement,
                                    display: true,
                                })
                            }
                            onMouseLeave={() =>
                                onPreviewToggle?.({
                                    asset,
                                    display: false,
                                })
                            }
                            onClick={e =>
                                onPreviewToggle?.({
                                    asset,
                                    anchorEl: (e.target as HTMLElement).closest(
                                        `.${assetClasses.thumbWrapper}`
                                    ) as HTMLElement,
                                    display: true,
                                    lock: true,
                                })
                            }
                            color={'info'}
                            icon={<AssetTypeIcon mimeType={main!.file!.type} />}
                        />
                    )}
                </div>
            ) : null}
        </div>
    );
}

export default React.memo(AssetThumb);

export function createAnimatedThumbStyle(): SxProps {
    return {
        [`.${assetClasses.animatedThumb}`]: {
            display: 'none',
        },
        '&:hover': {
            [`.${assetClasses.animatedThumb}`]: {
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

            ...createAnimatedThumbStyle(),
            ...createStorySx(thumbSize, theme),
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
        [`.${assetClasses.assetIcon}`]: {
            'width': '100%',
            'height': '100%',
            'minWidth': thumbSize,
            'minHeight': thumbSize,
            'display': 'flex',
            'alignItems': 'center',
            'justifyContent': 'center',
            '.MuiSvgIcon-root': {
                fontSize: thumbSize / 3,
                color: theme.palette.primary.main,
            },
        },
    };
};
