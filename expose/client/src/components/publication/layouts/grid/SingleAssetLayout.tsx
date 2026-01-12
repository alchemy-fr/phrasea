import {FilePlayer, videoPlayerSx} from '@alchemy/phrasea-framework';
import React, {useRef} from 'react';
import {Publication} from '../../../../types.ts';
import {useWindowSize} from '@alchemy/react-hooks/src/useWindowSize.ts';
import {Box, Theme, useMediaQuery, useTheme} from '@mui/material';
import {SystemCssProperties} from '@mui/system';
import {useTracker} from '../../../../hooks/useTracker.ts';

type Props = {
    publication: Publication;
};

export default function SingleAssetLayout({publication}: Props) {
    const {innerWidth: windowWidth, innerHeight: windowHeight} =
        useWindowSize();
    const containerRef = useRef<HTMLDivElement>(null);

    const theme = useTheme();
    const isSmallScreen = useMediaQuery(theme.breakpoints.down('md'));
    const headerHeight = 200;
    const mediaHeight = isSmallScreen
        ? windowHeight * 0.6
        : windowHeight - headerHeight;

    const asset = publication.assets[0];

    useTracker({
        containerRef,
        asset,
    });

    return (
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
    );
}
