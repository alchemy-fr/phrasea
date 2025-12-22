import PublicationHeader from '../../../layouts/shared-components/PublicationHeader.tsx';
import React from 'react';
import {Box} from '@mui/material';
import {useThumbs} from '../../../../hooks/useThumbs.tsx';
import {Link} from '@alchemy/navigation';
import {LayoutProps} from '../types.ts';
import Lightbox from '../../Asset/Lightbox.tsx';

export default function GridLayout({publication, assetId}: LayoutProps) {
    const thumbs = useThumbs({
        publicationId: publication.id,
        assets: publication.assets,
    });

    console.log('assetId', assetId);

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
                sx={{
                    display: 'flex',
                    flexWrap: 'wrap',
                    gap: 0.5,
                }}
            >
                {thumbs.map(t => (
                    <Link to={t.path} key={t.id}>
                        <img
                            src={t.src}
                            alt={t.alt}
                            style={{height: 180, objectFit: 'cover'}}
                        />
                    </Link>
                ))}
            </Box>
        </>
    );
}
