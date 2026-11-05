'use client';

import {type PointerEvent, type ReactNode, useRef} from 'react';
import {
    boxFromPoints,
    clamp,
    isPointInBox,
    normalizeBox,
    type Box,
} from '@/features/annotations/geometry';
import type {Point} from '@/features/annotations/types';
import {CropOverlay} from '@/features/assets/crop/CropOverlay';
import {cn} from '@/lib/utils/cn';
import type {ClipCrop} from './types';

/** Below this size (relative), a drawn rectangle is taken as a mere click */
const minSize = 0.01;

type Corner = 'nw' | 'ne' | 'sw' | 'se';
const corners: Corner[] = ['nw', 'ne', 'sw', 'se'];

type Gesture =
    | {mode: 'draw' | 'resize'; anchor: Point; previous?: ClipCrop}
    | {mode: 'move'; origin: Point; crop: ClipCrop};

const toBox = ({x, y, w, h}: ClipCrop): Box => ({x, y, w, h});

/**
 * Rectangle from `anchor` to `point` (relative units), at the given ratio
 * (width / height in media pixels) when set: shrunk to fit, so that it stays
 * within the media.
 */
export function constrainedBox(
    anchor: Point,
    point: Point,
    ratio: number | undefined,
    mediaAspect: number
): ClipCrop {
    let w = Math.abs(point.x - anchor.x);
    let h = Math.abs(point.y - anchor.y);
    if (ratio) {
        // Relative height of a relative width at that ratio
        const k = mediaAspect / ratio;
        if (w * k > h) {
            w = h / k;
        } else {
            h = w * k;
        }
    }

    return normalizeBox({
        x: anchor.x,
        y: anchor.y,
        w: point.x >= anchor.x ? w : -w,
        h: point.y >= anchor.y ? h : -h,
    });
}

/**
 * The crop brought to the given ratio around its center (the largest
 * centered one when there is no crop yet), within the media.
 */
export function fitCropToRatio(
    crop: ClipCrop | undefined,
    ratio: number,
    mediaAspect: number
): ClipCrop {
    const k = mediaAspect / ratio;
    const center = crop
        ? {x: crop.x + crop.w / 2, y: crop.y + crop.h / 2}
        : {x: 0.5, y: 0.5};
    let w = crop ? Math.sqrt((crop.w * crop.h) / k) : 1;
    let h = w * k;
    // Fit within the media
    const scale = Math.min(1, 1 / w, 1 / h);
    w *= scale;
    h *= scale;

    return {
        x: clamp(center.x - w / 2, 0, 1 - w),
        y: clamp(center.y - h / 2, 0, 1 - h),
        w,
        h,
    };
}

/**
 * Draws, moves and resizes (by the corners) a crop rectangle over a media,
 * in coordinates relative to the media (0..1). The media (`children`) fills
 * a box at its ratio, as large as the editor allows.
 *
 * The media stays the same element whether the editor is `enabled` or not,
 * and once its ratio is known: a video is not reloaded.
 */
export function CropRectEditor({
    value,
    onChange,
    ratio,
    mediaAspect,
    enabled = true,
    className,
    children,
}: {
    value: ClipCrop | undefined;
    onChange: (crop: ClipCrop | undefined) => void;
    /** Width / height of the crop in media pixels, free when undefined */
    ratio?: number;
    /** Width / height of the media, undefined until it is loaded */
    mediaAspect: number | undefined;
    enabled?: boolean;
    className?: string;
    children: ReactNode;
}) {
    const gesture = useRef<Gesture | null>(null);

    const toPoint = (e: PointerEvent<HTMLDivElement>): Point => {
        const rect = e.currentTarget.getBoundingClientRect();

        return {
            x: clamp((e.clientX - rect.left) / (rect.width || 1), 0, 1),
            y: clamp((e.clientY - rect.top) / (rect.height || 1), 0, 1),
        };
    };

    const onPointerDown = (e: PointerEvent<HTMLDivElement>) => {
        if (e.button !== 0) {
            return;
        }
        const p = toPoint(e);
        const corner = (e.target as HTMLElement).dataset?.corner as
            Corner | undefined;
        if (value && corner) {
            // The anchor is the opposite corner
            gesture.current = {
                mode: 'resize',
                anchor: {
                    x: corner.includes('w') ? value.x + value.w : value.x,
                    y: corner.includes('n') ? value.y + value.h : value.y,
                },
                previous: value,
            };
        } else if (value && isPointInBox(p, toBox(value))) {
            gesture.current = {mode: 'move', origin: p, crop: value};
        } else {
            gesture.current = {mode: 'draw', anchor: p, previous: value};
        }
        e.currentTarget.setPointerCapture?.(e.pointerId);
        e.preventDefault();
    };

    const onPointerMove = (e: PointerEvent<HTMLDivElement>) => {
        const g = gesture.current;
        if (!g || !mediaAspect) {
            return;
        }
        const p = toPoint(e);
        if (g.mode === 'move') {
            onChange({
                ...g.crop,
                x: clamp(g.crop.x + p.x - g.origin.x, 0, 1 - g.crop.w),
                y: clamp(g.crop.y + p.y - g.origin.y, 0, 1 - g.crop.h),
            });

            return;
        }
        onChange(
            ratio
                ? constrainedBox(g.anchor, p, ratio, mediaAspect)
                : boxFromPoints(g.anchor, p)
        );
    };

    const onPointerUp = (e: PointerEvent<HTMLDivElement>) => {
        const g = gesture.current;
        gesture.current = null;
        e.currentTarget.releasePointerCapture?.(e.pointerId);
        if (!g || g.mode === 'move' || !mediaAspect) {
            return;
        }
        const p = toPoint(e);
        const box = ratio
            ? constrainedBox(g.anchor, p, ratio, mediaAspect)
            : boxFromPoints(g.anchor, p);
        if (box.w < minSize || box.h < minSize) {
            onChange(g.previous);
        }
    };

    return (
        <div
            className={cn(
                'relative flex size-full items-center justify-center',
                className
            )}
            style={{containerType: 'size'}}
        >
            <div
                className="relative"
                style={
                    mediaAspect
                        ? {
                              aspectRatio: mediaAspect,
                              width: `min(100cqw, calc(100cqh * ${mediaAspect}))`,
                          }
                        : {position: 'absolute', inset: 0}
                }
            >
                {children}
                {enabled && mediaAspect ? (
                    <div
                        className="absolute inset-0 cursor-crosshair touch-none select-none"
                        data-testid="crop-editor"
                        onPointerDown={onPointerDown}
                        onPointerMove={onPointerMove}
                        onPointerUp={onPointerUp}
                        onPointerCancel={onPointerUp}
                    >
                        {value ? (
                            <CropOverlay
                                crop={value}
                                dimmed={false}
                                className="cursor-move"
                            >
                                {corners.map(c => (
                                    <span
                                        key={c}
                                        data-corner={c}
                                        className={cn(
                                            'pointer-events-auto absolute size-3 rounded-sm border-2 border-primary bg-background',
                                            c.includes('n')
                                                ? '-top-1.5'
                                                : '-bottom-1.5',
                                            c.includes('w')
                                                ? '-left-1.5'
                                                : '-right-1.5',
                                            c === 'nw' || c === 'se'
                                                ? 'cursor-nwse-resize'
                                                : 'cursor-nesw-resize'
                                        )}
                                    />
                                ))}
                            </CropOverlay>
                        ) : null}
                    </div>
                ) : null}
            </div>
        </div>
    );
}
