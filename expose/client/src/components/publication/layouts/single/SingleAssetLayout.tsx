import {FilePlayer, videoPlayerSx} from '@alchemy/phrasea-framework';
import React, {useRef} from 'react';
import {useWindowSize} from '@alchemy/react-hooks/src/useWindowSize.ts';
import {Box, Container, Theme, useMediaQuery, useTheme} from '@mui/material';
import {SystemCssProperties} from '@mui/system';
import {useTracker} from '../../../../hooks/useTracker.ts';
import {LayoutProps} from '../types.ts';
import AssetLegend from '../../asset/AssetLegend.tsx';
import {useTranslation} from 'react-i18next';

type Props = {} & LayoutProps;

export default function SingleAssetLayout({publication}: Props) {
    const {t} = useTranslation();
    const {innerWidth: windowWidth, innerHeight: windowHeight} =
        useWindowSize();
    const containerRef = useRef<HTMLDivElement>(null);

    const theme = useTheme();
    const isSmallScreen = useMediaQuery(theme.breakpoints.down('md'));
    const headerHeight = 350;
    const mediaHeight = isSmallScreen
        ? windowHeight * 0.6
        : windowHeight - headerHeight;

    const asset = publication.assets[0];

    useTracker({
        containerRef,
        asset,
    });

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
                ref={containerRef}
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
            </Box>
            <Container
                sx={{
                    p: 2,
                }}
            >
                <AssetLegend publication={publication} asset={asset} />
            </Container>
        </>
    );
}
