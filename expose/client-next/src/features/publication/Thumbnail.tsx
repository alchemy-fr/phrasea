'use client';

import {useState} from 'react';
import type {Asset} from '@/types/api';
import {FileKindIcon} from '@/components/ui/file-icon';
import {FileKind, getFileKind} from '@/lib/utils/mime';
import {cn} from '@alchemy/next-ui/utils/cn';

/** The image standing for an asset: its thumbnail, else its preview image */
export function getThumbSrc(asset: Asset): string | undefined {
    if (
        asset.thumbUrl &&
        getFileKind(asset.thumbType || asset.mimeType) === FileKind.Image
    ) {
        return asset.thumbUrl;
    }
    if (
        asset.previewUrl &&
        getFileKind(asset.previewType || asset.mimeType) === FileKind.Image
    ) {
        return asset.previewUrl;
    }

    return undefined;
}

/** Thumbnail of an asset, the icon of its type when there is none */
export function Thumbnail({
    asset,
    className,
    imgClassName,
    onSize,
}: {
    asset: Asset;
    className?: string;
    imgClassName?: string;
    /** The natural size of the image, once loaded */
    onSize?: (width: number, height: number) => void;
}) {
    const src = getThumbSrc(asset);
    const [failed, setFailed] = useState(false);

    if (!src || failed) {
        return (
            <div
                className={cn(
                    'flex items-center justify-center bg-muted',
                    className
                )}
            >
                <FileKindIcon mimeType={asset.mimeType} />
            </div>
        );
    }

    return (
        <div className={cn('overflow-hidden bg-muted', className)}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
                src={src}
                alt={asset.title || asset.originalName || ''}
                loading="lazy"
                draggable={false}
                className={cn('size-full object-cover', imgClassName)}
                onLoad={e => {
                    const {naturalWidth, naturalHeight} = e.currentTarget;
                    if (naturalWidth > 0 && naturalHeight > 0) {
                        onSize?.(naturalWidth, naturalHeight);
                    }
                }}
                onError={() => setFailed(true)}
            />
        </div>
    );
}
