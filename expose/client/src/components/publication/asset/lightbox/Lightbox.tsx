import {Box, IconButton, Theme, useMediaQuery, useTheme} from '@mui/material';
import {Asset, Publication, Thumb} from '../../../../types.ts';
import React, {useEffect, useRef} from 'react';
import {
    FilePlayer,
    FilePlayerClasses,
    videoPlayerSx,
} from '@alchemy/phrasea-framework';
import ArrowLeftIcon from '@mui/icons-material/ArrowLeft';
import ArrowRightIcon from '@mui/icons-material/ArrowRight';
import CloseIcon from '@mui/icons-material/Close';
import classnames from 'classnames';
import classNames from 'classnames';
import AssetLegend from '../AssetLegend.tsx';
import {useWindowSize} from '@alchemy/react-hooks/src/useWindowSize.ts';
import {SystemCssProperties} from '@mui/system';
import {useTracker} from '../../../../hooks/useTracker.ts';
import {LightboxClasses} from './types.ts';
import Thumbs from './Thumbs.tsx';
import {useThumbNavigation} from './useThumbNavigation.ts';

type Props = {
    thumbs: Thumb[];
    asset: Asset;
    publication: Publication;
};

export default function Lightbox({publication, thumbs, asset}: Props) {
    const containerRef = useRef<HTMLDivElement>(null);

    useTracker({
        containerRef,
        asset,
    });

    useEffect(() => {
        const originalOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = originalOverflow;
        };
    }, []);

    const {close, goNext, goPrevious} = useThumbNavigation({
        publication,
        thumbs,
        asset,
    });

    const {innerWidth: windowWidth, innerHeight: windowHeight} =
        useWindowSize();
    const theme = useTheme();
    const isSmallScreen = useMediaQuery(theme.breakpoints.down('md'));
    const thumbHeight = 80;
    const thumbPadding = 2;
    const thumbOuterHeight =
        thumbHeight + parseFloat(theme.spacing(thumbPadding)) * 2;
    const mediaHeight = isSmallScreen
        ? windowHeight * 0.6
        : windowHeight - thumbOuterHeight;

    return (
        <div
            className={LightboxClasses.Lightbox}
            style={{
                position: 'fixed',
                top: 0,
                left: 0,
                bottom: 0,
                right: 0,
                backgroundColor: 'rgba(0, 0, 0, 0.80)',
                zIndex: 1300,
            }}
        >
            <Box
                sx={{
                    'display': 'flex',
                    'flexDirection': 'column',
                    'justifyContent': 'center',
                    'alignItems': 'center',
                    'height': '100vh',
                    '*::-webkit-scrollbar-thumb': {
                        backgroundColor: theme.palette.common.white,
                    },
                }}
            >
                <Box
                    sx={{
                        position: 'relative',
                        flexGrow: 1,
                        flexShrink: 1,
                        width: '100%',
                        [`.${LightboxClasses.Controls}`]: {
                            'opacity': 0,
                            'transition': 'opacity 0.3s ease',
                            'position': 'absolute',
                            'cursor': 'pointer',
                            'zIndex': 1,
                            '.MuiIconButton-root': {
                                color: 'white',
                                background: 'rgba(0, 0, 0, 0.2)',
                            },
                            '.MuiSvgIcon-root': {
                                fontSize: 25,
                            },
                        },
                        [`.${LightboxClasses.Arrow}`]: {
                            'top': '50%',
                            'transform': 'translateY(-50%)',
                            '.MuiSvgIcon-root': {
                                fontSize: 45,
                            },
                        },
                        [`&:hover .${LightboxClasses.Controls}`]: {
                            opacity: 1,
                        },
                        [`&:hover:has(.${FilePlayerClasses.PlayerControls}:hover) .${LightboxClasses.Arrow}`]:
                            {
                                display: 'none',
                            },
                    }}
                >
                    <Box
                        className={classNames(
                            LightboxClasses.Controls,
                            LightboxClasses.Close
                        )}
                        sx={theme => ({
                            top: theme.spacing(2),
                            right: theme.spacing(2),
                        })}
                    >
                        <IconButton onClick={() => close()}>
                            <CloseIcon />
                        </IconButton>
                    </Box>
                    <Box
                        className={classnames(
                            LightboxClasses.Controls,
                            LightboxClasses.Arrow
                        )}
                        sx={theme => ({
                            left: theme.spacing(2),
                        })}
                    >
                        <IconButton onClick={() => goPrevious()}>
                            <ArrowLeftIcon />
                        </IconButton>
                    </Box>
                    <Box
                        className={classnames(
                            LightboxClasses.Controls,
                            LightboxClasses.Arrow
                        )}
                        sx={theme => ({
                            right: theme.spacing(2),
                        })}
                    >
                        <IconButton onClick={() => goNext()}>
                            <ArrowRightIcon />
                        </IconButton>
                    </Box>
                    <Box
                        sx={theme => ({
                            position: 'absolute',
                            top: 0,
                            right: 0,
                            bottom: 0,
                            left: 0,
                            maxWidth: '100%',
                            height: `calc(100vh - ${thumbHeight}px - ${theme.spacing(2 * thumbPadding)})`,
                            maxHeight: `calc(100vh - ${thumbHeight}px - ${theme.spacing(2 * thumbPadding)})`,
                            display: 'flex',
                            justifyContent: 'center',
                            alignItems: 'center',
                            gap: 2,
                            flexDirection: {
                                xs: 'column',
                                md: 'row',
                            },
                        })}
                    >
                        <Box
                            ref={containerRef}
                            className={LightboxClasses.MediaContainer}
                            sx={theme => ({
                                display: 'flex',
                                justifyContent: 'center',
                                alignItems: 'center',
                                flexShrink: 1,
                                maxWidth: '100%',
                                minWidth: 0,
                                maxHeight: mediaHeight,
                                img: {
                                    maxHeight: mediaHeight,
                                },
                                ...(videoPlayerSx(
                                    theme
                                ) as SystemCssProperties<Theme>),
                            })}
                        >
                            <FilePlayer
                                file={{
                                    id: asset.id,
                                    name: asset.title ?? 'Asset',
                                    type: asset.mimeType,
                                    url: asset.previewUrl,
                                }}
                                controls={true}
                                title={asset.title ?? 'Asset'}
                                trackingId={
                                    asset.trackingId ||
                                    asset.assetId ||
                                    asset.id
                                }
                                dimensions={{
                                    width: windowWidth,
                                    height: mediaHeight,
                                }}
                                webVTTLinks={asset.webVTTLinks}
                            />
                        </Box>
                        <Box
                            sx={{
                                m: 3,
                                color: 'white',
                                flexGrow: 1,
                                maxWidth: {
                                    xs: '100%',
                                    md: 400,
                                },
                                maxHeight: {
                                    xs: '40vh',
                                    md: '90%',
                                },
                                overflow: 'auto',
                            }}
                        >
                            <AssetLegend
                                publication={publication}
                                asset={asset}
                                displayDownload={true}
                            />
                        </Box>
                    </Box>
                </Box>
                <Thumbs
                    thumbs={thumbs}
                    thumbHeight={thumbHeight}
                    thumbPadding={thumbPadding}
                    asset={asset}
                    isDark={true}
                />
            </Box>
        </div>
    );
}
