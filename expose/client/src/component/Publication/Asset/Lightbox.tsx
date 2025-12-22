import {Box, IconButton, useMediaQuery, useTheme} from '@mui/material';
import {Asset, Thumb} from '../../../types.ts';
import {getPath, Link, useNavigate} from '@alchemy/navigation';
import {useEffect, useMemo} from 'react';
import {routes} from '../../../routes.ts';
import {FilePlayer} from '@alchemy/phrasea-framework';
import ArrowLeftIcon from '@mui/icons-material/ArrowLeft';
import ArrowRightIcon from '@mui/icons-material/ArrowRight';
import CloseIcon from '@mui/icons-material/Close';
import classnames from 'classnames';
import AssetLegend from './AssetLegend.tsx';
import {useWindowSize} from '@alchemy/react-hooks/src/useWindowSize.ts';

type Props = {
    thumbs: Thumb[];
    asset: Asset;
    publicationId: string;
};
enum Classes {
    Lightbox = 'lightbox',
    Controls = 'lb-controls',
    Arrow = 'lb-arrow',
    Thumbnail = 'lb-thumbnail',
    SelectedThumbnail = 'lb-thumbnail-selected',
}

export default function Lightbox({publicationId, thumbs, asset}: Props) {
    const navigate = useNavigate();

    const {close, goNext, goPrevious} = useMemo(() => {
        const handler = (inc: number) => () => {
            const currentIndex = thumbs.findIndex(t => t.id === asset.id);
            const newIndex =
                (currentIndex + inc + thumbs.length) % thumbs.length;

            navigate(
                getPath(routes.publication.routes.asset, {
                    id: publicationId,
                    assetId: thumbs[newIndex].id,
                })
            );
        };

        return {
            goNext: handler(1),
            goPrevious: handler(-1),
            close: () => {
                navigate(
                    getPath(routes.publication, {
                        id: publicationId,
                    })
                );
            },
        };
    }, [thumbs, navigate, publicationId, asset]);

    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                close();
            } else if (event.key === 'ArrowRight') {
                goNext();
            } else if (event.key === 'ArrowLeft') {
                goPrevious();
            }
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => {
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

    return (
        <div
            className={Classes.Lightbox}
            style={{
                position: 'absolute',
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
                    display: 'flex',
                    flexDirection: 'column',
                    justifyContent: 'center',
                    alignItems: 'center',
                    height: '100vh',
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
                    }}
                >
                    <Box
                        className={Classes.Controls}
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
                            sx={_theme => ({
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
                            })}
                        >
                            <FilePlayer
                                file={{
                                    id: asset.id,
                                    name: asset.title ?? 'Asset',
                                    type: asset.mimeType,
                                    url: asset.previewUrl,
                                }}
                                title={asset.title ?? 'Asset'}
                                dimensions={{
                                    width: windowWidth,
                                    height: mediaHeight,
                                }}
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
                            <AssetLegend asset={asset} />
                        </Box>
                    </Box>
                </Box>
                <Box
                    sx={theme => ({
                        display: 'flex',
                        flexDirection: 'row',
                        gap: 1,
                        p: thumbPadding,
                        justifyContent: 'center',
                        [`.${Classes.Thumbnail}`]: {
                            display: 'block',
                            borderRadius: 2,
                            boxShadow: '0 4px 8px rgba(0, 0, 0, 0.2)',
                            maxHeight: thumbHeight,
                            [`&.${Classes.SelectedThumbnail}`]: {
                                outline: `3px solid ${theme.palette.primary.contrastText}`,
                            },
                        },
                    })}
                >
                    {thumbs.map(t => (
                        <Link to={t.path} key={t.id}>
                            <img
                                key={t.id}
                                src={t.src}
                                alt={t.alt}
                                className={classnames({
                                    [Classes.Thumbnail]: true,
                                    [Classes.SelectedThumbnail]:
                                        t.id === asset.id,
                                })}
                            />
                        </Link>
                    ))}
                </Box>
            </Box>
        </div>
    );
}
