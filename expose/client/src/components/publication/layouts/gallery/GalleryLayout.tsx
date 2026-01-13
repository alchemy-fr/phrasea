import React from 'react';
import {Box} from '@mui/material';
import {useThumbs} from '../../../../hooks/useThumbs.tsx';
import {LayoutProps} from '../types.ts';
import Lightbox from '../../asset/Lightbox.tsx';

type Props = {} & LayoutProps;

export default function GalleryLayout({publication, assetId}: Props) {
    const thumbs = useThumbs({
        publication: publication,
        assets: publication.assets!,
    });

    const selectedAsset = assetId
        ? publication.assets!.find(a => a.id === assetId)
        : publication.assets![0];

    if (!selectedAsset) {
        return null;
    }

    return (
        <>
            <Box>
                <Lightbox
                    thumbs={thumbs}
                    asset={selectedAsset}
                    publication={publication}
                />
            </Box>
        </>
    );
}
