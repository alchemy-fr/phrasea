'use client';

import type {ReactNode} from 'react';
import {cn} from '@/lib/utils/cn';
import type {RelativeBox} from './ratios';

const fullBox: RelativeBox = {left: 0, top: 0, width: 1, height: 1};

/**
 * Darkens what a crop leaves out. `crop` is relative to the media, `media` is
 * where the media is rendered, relative to the overlay box (the whole box by
 * default). `children` is rendered inside the kept rectangle (handles).
 * With `dimmed`, the kept part is slightly darkened too.
 */
export function CropOverlay({
    crop,
    media = fullBox,
    dimmed = true,
    className,
    children,
}: {
    crop: {x: number; y: number; w: number; h: number};
    media?: RelativeBox;
    dimmed?: boolean;
    className?: string;
    children?: ReactNode;
}) {
    const style = {
        left: `${(media.left + crop.x * media.width) * 100}%`,
        top: `${(media.top + crop.y * media.height) * 100}%`,
        width: `${crop.w * media.width * 100}%`,
        height: `${crop.h * media.height * 100}%`,
    };

    return (
        <>
            {dimmed ? (
                <div className="pointer-events-none absolute inset-0 bg-black/40" />
            ) : null}
            <div
                className={cn(
                    'pointer-events-none absolute border-2 border-primary shadow-[0_0_0_9999px_rgba(0,0,0,0.45)]',
                    className
                )}
                style={style}
                data-testid="crop-rect"
            >
                {children}
            </div>
        </>
    );
}
