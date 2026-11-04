'use client';

import {CSSProperties, useState} from 'react';
import {ImageOffIcon} from 'lucide-react';
import type {Asset, FaceBox} from '@/types/api';
import {cn} from '@/lib/utils/cn';

/** Image area around the face, relative to its longest side */
const context = 1.8;

/**
 * The face cropped out of the asset preview: the preview is scaled and
 * offset inside a square so that the box lands in the middle, with some
 * context around it. The box is normalized, so the crop only needs the
 * natural size of the image once loaded.
 */
export function FaceCrop({
    asset,
    box,
    size = 160,
    className,
}: {
    asset: Asset;
    box: FaceBox;
    size?: number;
    className?: string;
}) {
    const file = asset.preview?.file?.url
        ? asset.preview.file
        : asset.thumbnail?.file?.url
          ? asset.thumbnail.file
          : undefined;
    const [natural, setNatural] = useState<{
        url: string;
        w: number;
        h: number;
    }>();

    const dims = natural?.url === file?.url ? natural : undefined;
    let style: CSSProperties = {visibility: 'hidden'};
    if (dims) {
        const boxWidth = box.w * dims.w;
        const boxHeight = box.h * dims.h;
        const side = Math.max(boxWidth, boxHeight, 1) * context;
        const scale = size / side;
        const centerX = (box.x + box.w / 2) * dims.w;
        const centerY = (box.y + box.h / 2) * dims.h;
        style = {
            width: dims.w * scale,
            height: dims.h * scale,
            maxWidth: 'none',
            left: size / 2 - centerX * scale,
            top: size / 2 - centerY * scale,
        };
    }

    return (
        <div
            className={cn(
                'relative shrink-0 overflow-hidden bg-media-bg',
                className
            )}
            style={{width: size, height: size}}
            data-testid="face-crop"
        >
            {file ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img
                    src={file.url}
                    alt=""
                    draggable={false}
                    className="absolute"
                    style={style}
                    onLoad={e =>
                        setNatural({
                            url: file.url!,
                            w: e.currentTarget.naturalWidth,
                            h: e.currentTarget.naturalHeight,
                        })
                    }
                />
            ) : (
                <div className="flex size-full items-center justify-center text-muted-foreground">
                    <ImageOffIcon className="size-6 opacity-40" />
                </div>
            )}
        </div>
    );
}
