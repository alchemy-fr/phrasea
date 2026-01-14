import React, {useRef} from 'react';
import {Box, Container, IconButton, Theme, useTheme} from '@mui/material';
import {useThumbs} from '../../../../hooks/useThumbs.tsx';
import {LayoutProps} from '../types.ts';
import Thumbs from '../../asset/lightbox/Thumbs.tsx';
import {useThumbNavigation} from '../../asset/lightbox/useThumbNavigation.ts';
import {SystemCssProperties} from '@mui/system';
import {FilePlayer, videoPlayerSx} from '@alchemy/phrasea-framework';
import {useWindowSize} from '@alchemy/react-hooks/src/useWindowSize.ts';
import AssetLegend from '../../asset/AssetLegend.tsx';
import ArrowLeftIcon from '@mui/icons-material/ArrowLeft';
import ArrowRightIcon from '@mui/icons-material/ArrowRight';
import AssetIndex from '../../asset/lightbox/AssetIndex.tsx';
import {useMatchWindowWidthBreakpoint} from '@alchemy/react-hooks/src/useMatchWindowWidthBreakpoint.ts';
import {useTracker} from '../../../../hooks/useTracker.ts';
import {useTranslation} from 'react-i18next';

type Props = {} & LayoutProps;

export default function GalleryLayout({publication, assetId}: Props) {
    const {t} = useTranslation();
    const containerRef = useRef<HTMLDivElement>(null);
    const theme = useTheme();
    const thumbs = useThumbs({
        publication: publication,
        assets: publication.assets!,
    });

    const thumbsEnabled = thumbs.length > 1;

    const asset =
        (assetId
            ? publication.assets!.find(a => a.id === assetId)
            : undefined) ?? publication.assets![0];

    useTracker({
        containerRef,
        asset,
    });

    const {goPrevious, goNext} = useThumbNavigation({
        publication,
        thumbs,
        asset,
    });

    const {innerWidth: windowWidth} = useWindowSize();

    const mediaHeight = useMatchWindowWidthBreakpoint(theme, {
        xs: 450,
        md: 500,
        lg: 600,
        xl: 700,
    })!;

    if (!asset) {
        return (
            <>
                <Box>
                    {t('publication.noAssetAvailable', 'No asset available')}
                </Box>
            </>
        );
    }

    return (
        <>
            <Box
                sx={{
                    display: 'flex',
                    flexDirection: 'column',
                    justifyContent: 'center',
                    alignItems: 'center',
                }}
            >
                <Box
                    ref={containerRef}
                    sx={theme => ({
                        bgcolor: 'common.white',
                        width: '100%',
                        position: 'relative',
                        display: 'flex',
                        justifyContent: 'center',
                        alignItems: 'center',
                        flexShrink: 1,
                        minWidth: 0,
                        height: thumbsEnabled ? mediaHeight : undefined,
                        maxHeight: !thumbsEnabled ? mediaHeight : undefined,
                        img: {
                            maxHeight: mediaHeight,
                        },
                        ...(videoPlayerSx(theme) as SystemCssProperties<Theme>),
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
                    {thumbsEnabled && (
                        <Box
                            sx={_theme => ({
                                'position': 'absolute',
                                'bottom': 0,
                                'right': 0,
                                'display': 'flex',
                                'flexDirection': 'row',
                                'justifyContent': 'center',
                                'alignItems': 'center',
                                'p': 1,
                                '.MuiSvgIcon-root': {
                                    fontSize: 30,
                                },
                            })}
                        >
                            <div
                                style={{
                                    zIndex: 0,
                                    backgroundColor: 'rgba(255,255,255, 0.5)',
                                    filter: 'blur(16px)',
                                    position: 'absolute',
                                    top: 0,
                                    right: 0,
                                    bottom: 0,
                                    left: 0,
                                }}
                            />
                            <div
                                style={{
                                    zIndex: 1,
                                }}
                            >
                                <IconButton onClick={() => goPrevious()}>
                                    <ArrowLeftIcon />
                                </IconButton>
                            </div>
                            <AssetIndex
                                index={thumbs.findIndex(
                                    thumb => thumb.id === asset.id
                                )}
                                total={thumbs.length}
                            />
                            <div
                                style={{
                                    zIndex: 1,
                                }}
                            >
                                <IconButton onClick={() => goNext()}>
                                    <ArrowRightIcon />
                                </IconButton>
                            </div>
                        </Box>
                    )}
                </Box>

                {thumbsEnabled && (
                    <Thumbs
                        thumbs={thumbs}
                        asset={asset}
                        thumbPadding={2}
                        thumbHeight={80}
                    />
                )}

                <Container
                    sx={{
                        p: 2,
                    }}
                >
                    <AssetLegend
                        publication={publication}
                        asset={asset}
                        displayDownload={true}
                    />
                </Container>
            </Box>
        </>
    );
}
