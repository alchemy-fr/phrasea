'use client';

import {useEffect, useLayoutEffect, useRef, useState} from 'react';
import {cn} from '@alchemy/next-ui/utils/cn';
import {useDebouncedValue} from '@alchemy/next-ui/hooks/useDebouncedValue';
import {
    extractVideoFrames,
    getFilmstripCount,
    getFilmstripTimes,
} from './filmstrip';

/**
 * Draws the current frame of `video` on `canvas`, covering it (like
 * `object-fit: cover`), at the resolution of the screen.
 *
 * A cross-origin video taints the canvas: it is still shown, its pixels just
 * cannot be read back, so the frame is never turned into a data URL.
 */
function drawFrame(canvas: HTMLCanvasElement, video: HTMLVideoElement) {
    const dpr = window.devicePixelRatio || 1;
    const width = Math.round(canvas.clientWidth * dpr);
    const height = Math.round(canvas.clientHeight * dpr);
    const {videoWidth, videoHeight} = video;
    if (!width || !height || !videoWidth || !videoHeight) {
        return;
    }
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        return;
    }
    const scale = Math.max(width / videoWidth, height / videoHeight);
    const sw = width / scale;
    const sh = height / scale;
    try {
        ctx.drawImage(
            video,
            (videoWidth - sw) / 2,
            (videoHeight - sh) / 2,
            sw,
            sh,
            0,
            0,
            width,
            height
        );
    } catch {
        // The thumbnail stays empty
    }
}

/**
 * Thumbnails of a video, side by side, filling the width of the box: the
 * background of the clip timeline. Decorative.
 *
 * The frames are taken by a video element of its own (the player is left
 * alone), seeking to each instant in turn: the thumbnails appear one after
 * the other. A frame which fails to load stays empty, silently.
 */
export function VideoFilmstrip({
    src,
    duration,
    aspect,
    className,
}: {
    src: string;
    duration: number;
    /** Width / height of the video, 16/9 when unknown */
    aspect?: number;
    className?: string;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const canvases = useRef<(HTMLCanvasElement | null)[]>([]);
    const [size, setSize] = useState({width: 0, height: 0});

    useLayoutEffect(() => {
        const el = ref.current;
        if (!el) {
            return;
        }
        setSize({width: el.clientWidth, height: el.clientHeight});
        if (typeof ResizeObserver === 'undefined') {
            return;
        }
        const observer = new ResizeObserver(([entry]) =>
            setSize({
                width: entry.contentRect.width,
                height: entry.contentRect.height,
            })
        );
        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    const ratio = aspect && aspect > 0 ? aspect : 16 / 9;
    const frameWidth = size.height * ratio;
    // Resizing restarts the extraction: only once it settles
    const count = useDebouncedValue(
        size.width > 0 && size.height > 0
            ? getFilmstripCount(size.width, size.height, ratio)
            : 0,
        200
    );

    useEffect(() => {
        if (count < 1 || !(duration > 0)) {
            return;
        }
        const video = document.createElement('video');
        video.muted = true;
        video.playsInline = true;
        video.preload = 'auto';
        video.src = src;
        const controller = new AbortController();

        void extractVideoFrames(
            video,
            getFilmstripTimes(duration, count),
            (i, v) => {
                const canvas = canvases.current[i];
                if (canvas) {
                    drawFrame(canvas, v);
                }
            },
            controller.signal
        );

        return () => {
            controller.abort();
            // Releases the download and the decoder
            video.removeAttribute('src');
            video.load();
        };
    }, [src, duration, count]);

    return (
        <div
            ref={ref}
            className={cn('flex overflow-hidden bg-muted', className)}
            aria-hidden
            data-testid="clip-filmstrip"
        >
            {Array.from({length: count}, (_, i) => (
                <canvas
                    key={i}
                    ref={el => {
                        canvases.current[i] = el;
                    }}
                    className="h-full flex-none"
                    style={{width: frameWidth}}
                />
            ))}
        </div>
    );
}
