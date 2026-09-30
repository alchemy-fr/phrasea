'use client';

import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    MaximizeIcon,
    MinusIcon,
    PlusIcon,
    RotateCcwIcon,
    RotateCwIcon,
} from 'lucide-react';
import {Button} from '@/components/ui/button';
import {Tooltip} from '@/components/ui/overlays';
import {cn} from '@/lib/utils/cn';
import {
    clampOffset,
    fitScale,
    initialView,
    nextZoomStep,
    toScale,
    toZoom,
    wheelFactor,
    zoomView,
    type ZoomMetrics,
    type ZoomView,
} from './zoom';

/**
 * Image with wheel / pinch zoom around the pointer, drag panning, zoom steps
 * and rotation by quarter turns. The rotation only affects the view: it is
 * forgotten with the image. Double-click toggles fit and actual size.
 */
export function ZoomableImage({
    src,
    alt,
    className,
    onInteraction,
}: {
    src: string;
    alt: string;
    className?: string;
    onInteraction?: () => void;
}) {
    const {t} = useTranslation();
    const containerRef = useRef<HTMLDivElement>(null);
    const imgRef = useRef<HTMLImageElement>(null);
    const [view, setView] = useState<ZoomView>(initialView);
    // Transitions for discrete steps (buttons, rotation), not for the wheel
    // or a drag which must follow the pointer
    const [animated, setAnimated] = useState(false);
    const [dragging, setDragging] = useState(false);
    const [metrics, setMetrics] = useState<ZoomMetrics>();
    const metricsRef = useRef<ZoomMetrics>(undefined);
    const viewRef = useRef(view);
    viewRef.current = view;
    const drag = useRef<{x: number; y: number; ox: number; oy: number}>(
        undefined
    );

    const measure = (): ZoomMetrics | undefined => {
        const c = containerRef.current;
        const img = imgRef.current;
        if (!c || !img || !img.offsetWidth) {
            return;
        }

        return {
            cw: c.clientWidth,
            ch: c.clientHeight,
            fw: img.offsetWidth,
            fh: img.offsetHeight,
            nw: img.naturalWidth || img.offsetWidth,
        };
    };

    const apply = (
        update: (m: ZoomMetrics, v: ZoomView) => ZoomView,
        smooth: boolean
    ) => {
        const m = measure();
        if (!m) {
            return;
        }
        setAnimated(smooth);
        setView(update(m, viewRef.current));
        onInteraction?.();
    };

    const zoomTo = (
        scale: (m: ZoomMetrics, v: ZoomView) => number,
        smooth: boolean,
        origin?: {x: number; y: number}
    ) => apply((m, v) => zoomView(m, v, scale(m, v), origin), smooth);

    const step = (direction: 1 | -1) =>
        zoomTo((m, v) => {
            const next = nextZoomStep(
                toZoom(m, v.scale),
                toZoom(m, fitScale(m, v.rotation)),
                direction
            );

            return next === undefined ? v.scale : toScale(m, next);
        }, true);
    const fit = () =>
        apply(
            (m, v) => ({...v, x: 0, y: 0, scale: fitScale(m, v.rotation)}),
            true
        );
    const actualSize = (origin?: {x: number; y: number}) =>
        zoomTo(m => toScale(m, 1), true, origin);
    const rotate = (quarter: 1 | -1) =>
        apply((m, v) => {
            const rotation = v.rotation + quarter * 90;

            return {rotation, x: 0, y: 0, scale: fitScale(m, rotation)};
        }, true);

    const fromCenter = (e: {clientX: number; clientY: number}) => {
        const rect = containerRef.current!.getBoundingClientRect();

        return {
            x: e.clientX - rect.left - rect.width / 2,
            y: e.clientY - rect.top - rect.height / 2,
        };
    };

    // Not through React: its wheel listeners are passive, the page would
    // scroll (or the browser zoom on a pinch)
    const onWheelRef = useRef<(e: WheelEvent) => void>(undefined);
    onWheelRef.current = e => {
        e.preventDefault();
        zoomTo((_m, v) => v.scale * wheelFactor(e), false, fromCenter(e));
    };
    useEffect(() => {
        const el = containerRef.current!;
        const listener = (e: WheelEvent) => onWheelRef.current?.(e);
        el.addEventListener('wheel', listener, {passive: false});

        return () => el.removeEventListener('wheel', listener);
    }, []);

    // The zoom level is shown relative to the natural size, which depends on
    // the viewport the image is fitted in
    useEffect(() => {
        const el = containerRef.current!;
        const observer = new ResizeObserver(() => {
            const prev = metricsRef.current;
            const m = measure();
            metricsRef.current = m;
            setMetrics(m);
            if (m) {
                // A fitted image stays fitted
                setView(v =>
                    prev && isFitted(prev, v)
                        ? {...v, x: 0, y: 0, scale: fitScale(m, v.rotation)}
                        : zoomView(m, v, v.scale)
                );
            }
        });
        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    const zoom = metrics ? toZoom(metrics, view.scale) : undefined;
    const fitted = !metrics || isFitted(metrics, view);
    // Smaller than its natural size once fitted
    const reduced =
        !!metrics && fitScale(metrics, view.rotation) < toScale(metrics, 1);

    return (
        <div
            ref={containerRef}
            data-testid="zoomable-image"
            className={cn(
                'relative size-full touch-none overflow-hidden select-none',
                dragging
                    ? 'cursor-grabbing'
                    : fitted
                      ? 'cursor-zoom-in'
                      : 'cursor-grab',
                className
            )}
            onClick={onInteraction}
            onPointerDown={e => {
                if (e.button !== 0 || inToolbar(e.target)) {
                    return;
                }
                e.currentTarget.setPointerCapture(e.pointerId);
                drag.current = {
                    x: e.clientX,
                    y: e.clientY,
                    ox: view.x,
                    oy: view.y,
                };
            }}
            onPointerMove={e => {
                const d = drag.current;
                if (!d) {
                    return;
                }
                const m = measure();
                if (!m) {
                    return;
                }
                setDragging(true);
                setAnimated(false);
                setView(v =>
                    clampOffset(m, {
                        ...v,
                        x: d.ox + e.clientX - d.x,
                        y: d.oy + e.clientY - d.y,
                    })
                );
            }}
            onPointerUp={() => {
                drag.current = undefined;
                setDragging(false);
            }}
            onPointerCancel={() => {
                drag.current = undefined;
                setDragging(false);
            }}
            onDoubleClick={e => {
                if (inToolbar(e.target)) {
                    return;
                }
                if (!fitted) {
                    fit();
                } else if (reduced) {
                    actualSize(fromCenter(e));
                } else {
                    zoomTo((_m, v) => v.scale * 2, true, fromCenter(e));
                }
            }}
        >
            <div className="flex size-full items-center justify-center">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img
                    ref={imgRef}
                    src={src}
                    alt={alt}
                    draggable={false}
                    className={cn(
                        'max-h-full max-w-full object-contain will-change-transform',
                        animated && 'transition-transform duration-200 ease-out'
                    )}
                    style={{
                        transform: `translate(${view.x}px, ${view.y}px) rotate(${view.rotation}deg) scale(${view.scale})`,
                    }}
                    onLoad={() => {
                        metricsRef.current = measure();
                        setMetrics(metricsRef.current);
                    }}
                />
            </div>
            <div
                data-zoom-toolbar
                className="absolute bottom-3 left-1/2 flex -translate-x-1/2 items-center gap-1 rounded-full border bg-background/90 p-1 shadow-md"
            >
                <Tooltip content={t('player.zoom_out', 'Zoom out')}>
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        onClick={() => step(-1)}
                        disabled={fitted}
                        aria-label={t('player.zoom_out', 'Zoom out')}
                    >
                        <MinusIcon />
                    </Button>
                </Tooltip>
                <Tooltip content={t('player.actual_size', 'Actual size')}>
                    <button
                        type="button"
                        data-testid="zoom-level"
                        className="h-6 w-12 rounded-full text-center font-mono text-xs hover:bg-accent"
                        onClick={() => actualSize()}
                    >
                        {zoom !== undefined ? `${Math.round(zoom * 100)}%` : ''}
                    </button>
                </Tooltip>
                <Tooltip content={t('player.zoom_in', 'Zoom in')}>
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        onClick={() => step(1)}
                        aria-label={t('player.zoom_in', 'Zoom in')}
                    >
                        <PlusIcon />
                    </Button>
                </Tooltip>
                <Tooltip content={t('player.fit', 'Fit to screen')}>
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        onClick={fit}
                        disabled={fitted}
                        aria-label={t('player.fit', 'Fit to screen')}
                    >
                        <MaximizeIcon />
                    </Button>
                </Tooltip>
                <span className="mx-0.5 h-4 w-px bg-border" />
                <Tooltip content={t('player.rotate_left', 'Rotate left')}>
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        onClick={() => rotate(-1)}
                        aria-label={t('player.rotate_left', 'Rotate left')}
                    >
                        <RotateCcwIcon />
                    </Button>
                </Tooltip>
                <Tooltip content={t('player.rotate_right', 'Rotate right')}>
                    <Button
                        variant="ghost"
                        size="icon-xs"
                        onClick={() => rotate(1)}
                        aria-label={t('player.rotate_right', 'Rotate right')}
                    >
                        <RotateCwIcon />
                    </Button>
                </Tooltip>
            </div>
        </div>
    );
}

function isFitted(m: ZoomMetrics, v: ZoomView): boolean {
    return (
        Math.abs(v.scale - fitScale(m, v.rotation)) < 1e-3 &&
        v.x === 0 &&
        v.y === 0
    );
}

function inToolbar(target: EventTarget): boolean {
    return target instanceof Element && !!target.closest('[data-zoom-toolbar]');
}
