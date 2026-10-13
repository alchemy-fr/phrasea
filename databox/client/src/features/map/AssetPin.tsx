'use client';

import type {Asset} from '@/types/api';
import {FileKindIcon} from '@/components/chips';
import {cn} from '@/lib/utils/cn';
import {useHoverPreview} from './useHoverPreview';

/**
 * Marker of a single asset: its thumbnail, or a dot until the asset is loaded.
 * Hovered, it shows the preview of the grid; clicked, it opens the asset.
 */
export function AssetPin({
    asset,
    onClick,
}: {
    asset: Asset | undefined;
    onClick: () => void;
}) {
    const hover = useHoverPreview(asset);
    const thumb = asset?.thumbnail?.file;
    const isImage = !!thumb?.url && thumb.type.startsWith('image/');

    return (
        <button
            type="button"
            data-testid="map-asset-pin"
            aria-label={asset?.name ?? asset?.id}
            onMouseEnter={hover.onMouseEnter}
            onMouseLeave={hover.onMouseLeave}
            onClick={e => {
                e.stopPropagation();
                hover.onMouseLeave();
                onClick();
            }}
            className={cn(
                'block cursor-pointer overflow-hidden rounded-md border-2 border-background bg-muted shadow-md ring-primary transition-transform hover:z-10 hover:scale-110 focus-visible:ring-2 focus-visible:outline-none',
                asset ? 'size-12' : 'size-4 rounded-full bg-primary'
            )}
        >
            {asset ? (
                isImage ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                        src={thumb.url}
                        alt=""
                        draggable={false}
                        className="size-full object-cover"
                    />
                ) : (
                    <span className="flex size-full items-center justify-center">
                        <FileKindIcon
                            mimeType={asset.source?.type}
                            className="size-6"
                        />
                    </span>
                )
            ) : null}
        </button>
    );
}
