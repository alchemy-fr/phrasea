import React from 'react';
import {Box} from '@mui/material';
import {useThumbs} from '../../../../hooks/useThumbs.tsx';
import {Link} from '@alchemy/navigation';
import {LayoutProps} from '../types.ts';
import Lightbox from '../../asset/Lightbox.tsx';
import {useContainerWidth} from '@alchemy/react-hooks/src/useContainerWidth.ts';
import {buildLayoutFlat} from './buildLayout.ts';
import {ThumbWithDimensions} from '../../../../types.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {ImageExtended} from './types.ts';
import {Classes} from '../../types.ts';
import AssetIconThumbnail, {thumbSx} from '../../asset/AssetIconThumbnail.tsx';

type Props = {
    rowHeight?: number;
    margin?: number;
} & LayoutProps;

export default function GridLayout({
    publication,
    assetId,
    rowHeight = 180,
    margin = 2,
}: Props) {
    const {containerRef, containerWidth} = useContainerWidth(window.innerWidth);
    const [resolvedThumbs, setResolvedThumbs] =
        React.useState<ImageExtended<ThumbWithDimensions>[]>();

    const thumbs = useThumbs({
        publication: publication,
        assets: publication.assets,
    });

    React.useEffect(() => {
        const loadThumbs = async () => {
            const resolvedThumbs = await Promise.all(
                thumbs.map(a => {
                    return new Promise(resolve => {
                        if (!a.src) {
                            return resolve({
                                ...a,
                                width: rowHeight,
                                height: rowHeight,
                            } as ThumbWithDimensions);
                        }

                        const img = new Image();
                        img.onload = () => {
                            resolve({
                                ...a,
                                width: img.width,
                                height: img.height,
                            } as ThumbWithDimensions);
                        };
                        img.onerror = e => {
                            console.error(e);
                            resolve({
                                ...a,
                                width: 100,
                                height: 100,
                            } as ThumbWithDimensions);
                        };
                        img.src = a.src;
                    });
                })
            );

            setResolvedThumbs(
                buildLayoutFlat(
                    resolvedThumbs as ImageExtended<ThumbWithDimensions>[],
                    {
                        containerWidth,
                        rowHeight,
                        margin,
                    }
                )
            );
        };

        loadThumbs();
    }, [thumbs, rowHeight, containerWidth]);

    const openedAsset = assetId
        ? publication.assets.find(a => a.id === assetId)
        : undefined;

    return (
        <>
            {openedAsset ? (
                <Lightbox
                    thumbs={thumbs}
                    asset={openedAsset}
                    publication={publication}
                />
            ) : null}
            <Box
                ref={containerRef}
                sx={theme => ({
                    display: 'flex',
                    flexWrap: 'wrap',
                    [`.${Classes.thumbContainer}`]: {
                        backgroundColor: theme.palette.background.paper,
                        overflow: 'hidden',
                        margin: `${margin}px`,
                        img: {
                            maxWidth: 'none',
                            marginTop: 0,
                            display: 'block',
                        },
                    },
                    ...thumbSx(theme),
                })}
            >
                {!resolvedThumbs ? (
                    <FullPageLoader backdrop={false} />
                ) : (
                    resolvedThumbs.map(t => (
                        <Link
                            to={t.path}
                            key={t.id}
                            style={{
                                width: t.viewportWidth,
                                height: t.scaledHeight,
                            }}
                            className={Classes.thumbContainer}
                        >
                            {t.src ? (
                                <img
                                    src={t.src}
                                    alt={t.alt}
                                    style={{
                                        width: t.scaledWidth,
                                        height: t.scaledHeight,
                                        marginLeft: t.marginLeft,
                                    }}
                                />
                            ) : (
                                <AssetIconThumbnail
                                    style={{
                                        width: t.scaledWidth,
                                        height: t.scaledHeight,
                                        marginLeft: t.marginLeft,
                                    }}
                                    mimeType={t.mimeType}
                                />
                            )}
                        </Link>
                    ))
                )}
            </Box>
        </>
    );
}
