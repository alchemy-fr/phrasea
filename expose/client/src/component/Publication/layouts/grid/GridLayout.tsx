import PublicationHeader from '../../../layouts/shared-components/PublicationHeader.tsx';
import React from 'react';
import {Box} from '@mui/material';
import {useThumbs} from '../../../../hooks/useThumbs.tsx';
import {Link} from '@alchemy/navigation';
import {LayoutProps} from '../types.ts';
import Lightbox from '../../Asset/Lightbox.tsx';
import {useContainerWidth} from '@alchemy/react-hooks/src/useContainerWidth.ts';
import {buildLayoutFlat} from './buildLayout.ts';
import {ThumbWithDimensions} from '../../../../types.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {ImageExtended} from './types.ts';

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
        publicationId: publication.id,
        assets: publication.assets,
    });

    React.useEffect(() => {
        const loadThumbs = async () => {
            const resolvedThumbs = await Promise.all(
                thumbs.map(a => {
                    return new Promise(resolve => {
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
            <PublicationHeader publication={publication} />
            {openedAsset ? (
                <Lightbox
                    thumbs={thumbs}
                    asset={openedAsset}
                    publicationId={publication.id}
                />
            ) : null}
            <Box
                ref={containerRef}
                sx={{
                    display: 'flex',
                    flexWrap: 'wrap',
                }}
            >
                {!resolvedThumbs ? (
                    <FullPageLoader backdrop={false} />
                ) : (
                    resolvedThumbs.map(t => (
                        <Link
                            to={t.path}
                            key={t.id}
                            style={{
                                margin,
                                width: t.viewportWidth,
                                height: t.scaledHeight,
                                overflow: 'hidden',
                            }}
                        >
                            <img
                                src={t.src}
                                alt={t.alt}
                                style={{
                                    maxWidth: 'none',
                                    width: t.scaledWidth,
                                    height: t.scaledHeight,
                                    marginLeft: t.marginLeft,
                                    marginTop: 0,
                                }}
                            />
                        </Link>
                    ))
                )}
            </Box>
        </>
    );
}
