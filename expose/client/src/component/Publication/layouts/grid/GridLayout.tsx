import PublicationHeader from '../../../layouts/shared-components/PublicationHeader.tsx';
import React, {useCallback} from 'react';
import {Asset, Publication} from '../../../../types.ts';
import {getThumbPlaceholder} from '../../../layouts/shared-components/placeholders.ts';
import squareImg from '../../../../images/square.svg';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {Box} from '@mui/material';

type Props = {
    data: Publication;
};

type CustomImage = {
    data: Asset;
    originalUrl: string;
    thumbSrc: string;
    caption: string;
    width: number;
    height: number;
};

export default function GridLayout({data}: Props) {
    const [images, setImages] = React.useState<CustomImage[] | undefined>();

    const loadImages = useCallback(async () => {
        setImages(
            (await Promise.all(
                data.assets.map(a => {
                    return new Promise((resolve, _reject) => {
                        const image = {
                            data: a,
                            originalUrl: a.previewUrl,
                            caption: a.title,
                            thumbSrc:
                                a.thumbUrl || getThumbPlaceholder(a.mimeType),
                        };

                        const img = new Image();
                        img.onload = () => {
                            resolve({
                                ...image,
                                width: img.width,
                                height: img.height,
                            } as CustomImage);
                        };
                        img.onerror = e => {
                            console.error(e);
                            resolve({
                                ...image,
                                thumbSrc: squareImg,
                                width: 100,
                                height: 100,
                            } as CustomImage);
                        };
                        img.src = image.thumbSrc;
                    });
                })
            )) as CustomImage[]
        );
    }, [data]);

    React.useEffect(() => {
        loadImages();
    }, [loadImages]);

    if (!images) {
        return <FullPageLoader backdrop={false} />;
    }

    return (
        <>
            <PublicationHeader data={data} />
            <div>
                <Box
                    sx={{
                        display: 'flex',
                        flexWrap: 'wrap',
                        gap: 0.5,
                    }}
                >
                    {images.map(i => (
                        <a
                            data-lg-size={`${i.width}-${i.height}`}
                            data-src={i.originalUrl}
                            data-sub-html={`<h4>${i.caption}</h4><p></p>`}
                        >
                            <img
                                src={i.thumbSrc}
                                alt={i.caption}
                                style={{height: 180, objectFit: 'cover'}}
                            />
                        </a>
                    ))}
                </Box>
            </div>
        </>
    );
}
