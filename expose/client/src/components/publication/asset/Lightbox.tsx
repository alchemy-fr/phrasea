import {Box, IconButton, Theme, useMediaQuery, useTheme} from '@mui/material';
import {Asset, Publication, Thumb} from '../../../types.ts';
import {Link} from '@alchemy/navigation';
import React, {useEffect, useMemo, useRef} from 'react';
import {
    FilePlayer,
    FilePlayerClasses,
    videoPlayerSx,
} from '@alchemy/phrasea-framework';
import ArrowLeftIcon from '@mui/icons-material/ArrowLeft';
import ArrowRightIcon from '@mui/icons-material/ArrowRight';
import CloseIcon from '@mui/icons-material/Close';
import classnames from 'classnames';
import AssetLegend from './AssetLegend.tsx';
import {useWindowSize} from '@alchemy/react-hooks/src/useWindowSize.ts';
import {SystemCssProperties} from '@mui/system';
import AssetIconThumbnail, {thumbSx} from './AssetIconThumbnail.tsx';
import classNames from 'classnames';
import {useTracker} from '../../../hooks/useTracker.ts';
import {useNavigateToPublication} from '../../../hooks/useNavigateToPublication.ts';

type Props = {
    thumbs: Thumb[];
    asset: Asset;
    publication: Publication;
};
enum Classes {
    Lightbox = 'lightbox',
    Controls = 'lb-controls',
    Close = 'lb-close',
    Arrow = 'lb-arrow',
    ThumbnailContainer = 'lb-thumbnail-container',
    SelectedThumbnail = 'lb-thumbnail-selected',
    MediaContainer = 'lb-media-container',
}

export default function Lightbox({publication, thumbs, asset}: Props) {
    const navigateToPublication = useNavigateToPublication();
    const containerRef = useRef<HTMLDivElement>(null);

    useTracker({
        containerRef,
        asset,
    });

    const {close, goNext, goPrevious} = useMemo(() => {
        const handler = (inc: number) => () => {
            const currentIndex = thumbs.findIndex(t => t.id === asset.id);
            const newIndex =
                (currentIndex + inc + thumbs.length) % thumbs.length;

            navigateToPublication(publication, thumbs[newIndex].id);
        };

        return {
            goNext: handler(1),
            goPrevious: handler(-1),
            close: () => {
                navigateToPublication(publication);
            },
        };
    }, [thumbs, navigateToPublication, publication, asset]);

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                close();
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                goNext();
            } else if (event.key === 'ArrowLeft') {
                event.preventDefault();
                goPrevious();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        const originalOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = originalOverflow;
            window.removeEventListener('keydown', handleKeyDown);
        };
    }, [goNext, goPrevious, close]);

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

    const thumbsContainerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const container = thumbsContainerRef.current;
        if (!container) {
            return;
        }
        const onWheel = (e: WheelEvent) => {
            if (e.deltaY === 0) return;
            e.preventDefault();
            container.scrollLeft += e.deltaY;
        };
        container.addEventListener('wheel', onWheel, {passive: false});

        return () => {
            container.removeEventListener('wheel', onWheel);
        };
    }, [thumbsContainerRef]);

    useEffect(() => {
        thumbsContainerRef.current
            ?.querySelector(`#t_${asset.id}`)
            ?.scrollIntoView({
                behavior: 'smooth',
                inline: 'center',
                block: 'nearest',
            });
    }, [asset]);

    return (
        <div
            className={Classes.Lightbox}
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
                        [`.${Classes.Controls}`]: {
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
                        [`.${Classes.Arrow}`]: {
                            'top': '50%',
                            'transform': 'translateY(-50%)',
                            '.MuiSvgIcon-root': {
                                fontSize: 45,
                            },
                        },
                        [`&:hover .${Classes.Controls}`]: {
                            opacity: 1,
                        },
                        [`&:hover:has(.${FilePlayerClasses.PlayerControls}:hover) .${Classes.Arrow}`]:
                            {
                                display: 'none',
                            },
                    }}
                >
                    <Box
                        className={classNames(Classes.Controls, Classes.Close)}
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
                        className={classnames(Classes.Controls, Classes.Arrow)}
                        sx={theme => ({
                            left: theme.spacing(2),
                        })}
                    >
                        <IconButton onClick={() => goPrevious()}>
                            <ArrowLeftIcon />
                        </IconButton>
                    </Box>
                    <Box
                        className={classnames(Classes.Controls, Classes.Arrow)}
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
                            className={Classes.MediaContainer}
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
                            />
                        </Box>
                    </Box>
                </Box>
                <Box
                    ref={thumbsContainerRef}
                    sx={_theme => ({
                        maxWidth: '100vw',
                        overflow: 'auto',
                    })}
                >
                    <Box
                        sx={theme => ({
                            display: 'flex',
                            flexDirection: 'row',
                            gap: 1,
                            width: 'fit-content',
                            p: thumbPadding,
                            justifyContent: 'center',
                            [`.${Classes.ThumbnailContainer}`]: {
                                backgroundColor: theme.palette.background.paper,
                                borderRadius: 2,
                                overflow: 'hidden',
                                boxShadow: '0 4px 8px rgba(0, 0, 0, 0.2)',
                                [`&.${Classes.SelectedThumbnail}`]: {
                                    outline: `3px solid ${theme.palette.primary.contrastText}`,
                                },
                                [`img`]: {
                                    minWidth: 0,
                                    display: 'block',
                                    maxHeight: thumbHeight,
                                },
                            },
                            ...thumbSx(theme, 30),
                        })}
                    >
                        {thumbs.map(t => (
                            <Link
                                id={`t_${t.id}`}
                                to={t.path}
                                key={t.id}
                                className={classnames({
                                    [Classes.ThumbnailContainer]: true,
                                    [Classes.SelectedThumbnail]:
                                        t.id === asset.id,
                                })}
                            >
                                {t.src ? (
                                    <img key={t.id} src={t.src} alt={t.alt} />
                                ) : (
                                    <AssetIconThumbnail
                                        mimeType={t.mimeType}
                                        style={{
                                            width: thumbHeight,
                                            height: thumbHeight,
                                        }}
                                    />
                                )}
                            </Link>
                        ))}
                    </Box>
                </Box>
            </Box>
        </div>
    );
}
