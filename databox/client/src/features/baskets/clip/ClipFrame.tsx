'use client';

import type {CSSProperties, ReactNode} from 'react';
import {cn} from '@alchemy/next-ui/utils/cn';
import type {ClipCrop} from './types';
import {cropAspect, cropFrameStyle} from './clip';

/**
 * Shows only the cropped part of a media, as large as its box allows (like
 * `object-fit: contain`): a frame at the crop ratio hides the overflow of the
 * media, positioned inside it. `object-view-box` would do the same but is
 * missing from Firefox and Safari.
 *
 * `aspect` is the width / height of the media, known once it is loaded: the
 * media is rendered hidden until then, to measure it. `children` renders the
 * media with the given style and class; the elements stay the same once the
 * size is known (a video is not reloaded).
 */
export function ClipFrame({
    crop,
    aspect,
    className,
    children,
}: {
    crop: ClipCrop;
    aspect: number | undefined;
    className?: string;
    children: (media: {style: CSSProperties; className: string}) => ReactNode;
}) {
    const ratio = aspect ? cropAspect(crop, aspect) : undefined;

    return (
        <div
            className={cn(
                'relative flex size-full items-center justify-center',
                className
            )}
            style={{containerType: 'size'}}
            data-testid="clip-frame"
        >
            <div
                className="relative overflow-hidden"
                style={
                    ratio
                        ? {
                              aspectRatio: ratio,
                              width: `min(100cqw, calc(100cqh * ${ratio}))`,
                          }
                        : {position: 'absolute', inset: 0}
                }
            >
                {children(
                    ratio
                        ? {
                              style: cropFrameStyle(crop),
                              className: 'block object-fill',
                          }
                        : {
                              style: {visibility: 'hidden'},
                              className: 'size-full',
                          }
                )}
            </div>
        </div>
    );
}
