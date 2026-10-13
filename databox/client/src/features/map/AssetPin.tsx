'use client';

import type {Asset} from '@/types/api';
import {FileKindIcon} from '@/components/chips';
import {cn} from '@/lib/utils/cn';

/**
 * Marker of a single asset: its thumbnail, or a dot until the asset is loaded.
 */
export function AssetPin({
    asset,
    active,
    onClick,
    onDoubleClick,
}: {
    asset: Asset | undefined;
    active?: boolean;
    onClick: () => void;
    onDoubleClick: () => void;
}) {
    const thumb = asset?.thumbnail?.file;
    const isImage = !!thumb?.url && thumb.type.startsWith('image/');

    return (
        <button
            type="button"
            data-testid="map-asset-pin"
            aria-label={asset?.name ?? asset?.id}
            title={asset?.name}
            onClick={e => {
                e.stopPropagation();
                onClick();
            }}
            onDoubleClick={e => {
                e.stopPropagation();
                onDoubleClick();
            }}
            className={cn(
                'block cursor-pointer overflow-hidden rounded-md border-2 border-background bg-muted shadow-md ring-primary transition-transform hover:z-10 hover:scale-110 focus-visible:ring-2 focus-visible:outline-none',
                asset ? 'size-12' : 'size-4 rounded-full bg-primary',
                active && 'scale-110 ring-2'
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
