import React from 'react';
import {Box, Theme, useMediaQuery, useTheme} from '@mui/material';
import {useThumbs} from '../../../../hooks/useThumbs.tsx';
import {LayoutProps} from '../types.ts';
import Thumbs from '../../asset/lightbox/Thumbs.tsx';
import {useThumbNavigation} from '../../asset/lightbox/useThumbNavigation.ts';
import {LightboxClasses} from '../../asset/lightbox/types.ts';
import {SystemCssProperties} from '@mui/system';
import {FilePlayer, videoPlayerSx} from '@alchemy/phrasea-framework';
import {useWindowSize} from '@alchemy/react-hooks/src/useWindowSize.ts';

type Props = {} & LayoutProps;

export default function GalleryLayout({publication, assetId}: Props) {
    const thumbs = useThumbs({
        publication: publication,
        assets: publication.assets!,
    });

    const asset =
        (assetId
            ? publication.assets!.find(a => a.id === assetId)
            : undefined) ?? publication.assets![0];

    const {} = useThumbNavigation({
        publication,
        thumbs,
        asset,
    });

    const {innerWidth: windowWidth, innerHeight: windowHeight} =
        useWindowSize();
    const theme = useTheme();
    const isSmallScreen = useMediaQuery(theme.breakpoints.down('md'));
    const mediaHeight = isSmallScreen ? windowHeight * 0.6 : 500;

    if (!asset) {
        return null;
    }

    return (
        <>
            <Box>
                <Box>
                    <Box
                        className={LightboxClasses.MediaContainer}
                        sx={theme => ({
                            display: 'flex',
                            justifyContent: 'center',
                            alignItems: 'center',
                            flexShrink: 1,
                            maxWidth: '100%',
                            minWidth: 0,
                            height: mediaHeight,
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
                </Box>
                <Thumbs
                    thumbs={thumbs}
                    asset={asset}
                    thumbPadding={2}
                    thumbHeight={150}
                />
            </Box>
        </>
    );
}
