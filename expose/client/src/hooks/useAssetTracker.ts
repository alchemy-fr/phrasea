import {Asset} from '../types.ts';
import {useEffect, useRef} from 'react';
import {FileTypeEnum, getFileTypeFromMIMEType} from '@alchemy/core';
import {useTracking} from '@alchemy/phrasea-framework';

type Props = {
    asset?: Asset;
};

export function useAssetTracker({asset}: Props) {
    const {scanForMedia, trackContentImpression} = useTracking();
    const containerRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        if (asset) {
            const type = getFileTypeFromMIMEType(asset.mimeType);
            if (containerRef.current) {
                trackContentImpression(
                    asset.trackingId || asset.assetId || asset.id,
                    asset.title
                );

                if ([FileTypeEnum.Audio, FileTypeEnum.Video].includes(type)) {
                    scanForMedia(containerRef);
                }
            }
        }
    }, [asset, containerRef, scanForMedia, trackContentImpression]);

    return {
        containerRef,
    };
}
