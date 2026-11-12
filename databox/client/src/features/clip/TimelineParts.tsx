'use client';

import {type KeyboardEvent, useEffect, useRef} from 'react';
import {cn} from '@alchemy/next-ui/utils/cn';
import {formatClipTime} from './clip';
import {drawCachedFrame} from './frameCanvas';
import type {PreviewFrame} from './useFramePreview';

/** Width of the frame shown on hover, in px */
const PREVIEW_WIDTH = 160;

/**
 * A handle of the selected range, at its start or end: dragged (see
 * `useTimelineDrag`, by its `data-drag`), or moved by the arrows once
 * focused.
 */
export function RangeHandle({
    edge,
    time,
    min,
    max,
    left,
    hidden,
    index,
    label,
    onKeyDown,
}: {
    edge: 'start' | 'end';
    time: number;
    min: number;
    max: number;
    /** In % of the view */
    left: number;
    /** Out of the view */
    hidden: boolean;
    index: number;
    label: string;
    onKeyDown: (e: KeyboardEvent<HTMLDivElement>) => void;
}) {
    return (
        <div
            role="slider"
            tabIndex={hidden ? -1 : 0}
            aria-label={label}
            aria-valuemin={min}
            aria-valuemax={max}
            aria-valuenow={time}
            aria-valuetext={formatClipTime(time)}
            data-drag={edge}
            data-index={index}
            data-testid={`clip-range-handle-${edge}`}
            className={cn(
                'absolute inset-y-0 z-10 w-3 -translate-x-1/2 cursor-ew-resize outline-none focus-visible:ring-2 focus-visible:ring-ring',
                hidden && 'invisible'
            )}
            style={{left: `${left}%`}}
            onKeyDown={onKeyDown}
        >
            <div className="pointer-events-none mx-auto h-full w-1.5 rounded-full bg-primary shadow-[0_0_0_1px_var(--background)]" />
        </div>
    );
}

/**
 * The frame (video) and the time under the cursor, above the timeline. The
 * frame shown may lag behind the cursor while it is taken.
 */
export function TimelineHoverPreview({
    time,
    frame,
    preview,
    aspect,
    percent,
}: {
    time: number;
    /** Number of the frame under the cursor, for a video */
    frame?: number;
    preview?: PreviewFrame;
    aspect?: number;
    /** Position of the cursor, in % of the timeline */
    percent: number;
}) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const ratio = aspect && aspect > 0 ? aspect : 16 / 9;
    const height = Math.min(120, Math.round(PREVIEW_WIDTH / ratio));
    const width = Math.round(height * ratio);
    const withFrame = frame !== undefined;
    // Kept within the timeline
    const margin = (withFrame ? width : 64) / 2 + 4;

    useEffect(() => {
        if (canvasRef.current && preview) {
            drawCachedFrame(canvasRef.current, preview.canvas);
        }
    }, [preview]);

    return (
        <div
            className="pointer-events-none absolute bottom-full z-20 mb-1 -translate-x-1/2 rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
            style={{
                left: `clamp(${margin}px, ${percent}%, calc(100% - ${margin}px))`,
            }}
            data-testid="clip-hover-preview"
            aria-hidden
        >
            {withFrame ? (
                <canvas
                    ref={canvasRef}
                    className="block rounded-sm bg-muted"
                    style={{width, height}}
                />
            ) : null}
            <div className="px-1 pt-0.5 text-center font-mono text-xs tabular-nums">
                {formatClipTime(time)}
                {withFrame ? ` · #${frame}` : null}
            </div>
        </div>
    );
}
