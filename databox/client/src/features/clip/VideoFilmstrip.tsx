'use client';

import {useEffect, useLayoutEffect, useRef, useState} from 'react';
import {cn} from '@alchemy/next-ui/utils/cn';
import {useDebouncedValue} from '@alchemy/next-ui/hooks/useDebouncedValue';
import {
    extractVideoFrames,
    getFilmstripCount,
    getFilmstripSlots,
} from './filmstrip';
import {
    captureFrame,
    createFrameVideo,
    drawCachedFrame,
    frameSeekTime,
    releaseVideo,
    type VideoFrameCache,
} from './frameCanvas';
import {frameIndex, type TimelineView} from './timeline';

/**
 * Thumbnails of the part of a video shown by the timeline, side by side,
 * filling the width of the box: the background of the clip timeline.
 * Decorative.
 *
 * The frames are taken by a video element of its own (the player is left
 * alone), kept while the source is the same, seeking to each instant in
 * turn: the thumbnails appear one after the other. A change of the view
 * restarts the extraction once it settles, and the frames already taken are
 * drawn again from the cache. A frame which fails to load stays empty,
 * silently.
 */
export function VideoFilmstrip({
    src,
    duration,
    view,
    frameDuration,
    cache,
    aspect,
    className,
}: {
    src: string;
    duration: number;
    view: TimelineView;
    frameDuration: number;
    cache: VideoFrameCache;
    /** Width / height of the video, 16/9 when unknown */
    aspect?: number;
    className?: string;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const canvases = useRef(new Map<number, HTMLCanvasElement>());
    const videoRef = useRef<HTMLVideoElement | undefined>(undefined);
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

    useEffect(
        () => () => {
            if (videoRef.current) {
                releaseVideo(videoRef.current);
                videoRef.current = undefined;
            }
        },
        [src]
    );

    const ratio = aspect && aspect > 0 ? aspect : 16 / 9;
    const count =
        size.width > 0 && size.height > 0
            ? getFilmstripCount(
                  size.width,
                  size.height,
                  ratio,
                  frameDuration > 0 ? view.span / frameDuration : undefined
              )
            : 0;
    const seen = new Set<number>();
    const slots = getFilmstripSlots(view, count, duration)
        .map(slot => {
            const frame = frameIndex(slot.time, frameDuration);

            return {
                ...slot,
                frame,
                seekTime: frameSeekTime(frame, frameDuration, duration),
            };
        })
        .filter(slot => {
            // Two slices may fall on the same frame at the deepest zoom
            if (seen.has(slot.frame)) {
                return false;
            }
            seen.add(slot.frame);

            return true;
        });
    const sliceWidth = count > 0 && view.span > 0 ? 100 / count : 0;

    // Scrolling or zooming restarts the extraction: only once it settles
    const pending = useDebouncedValue(
        slots
            .filter(s => !cache.has(s.frame))
            .map(s => `${s.frame}:${s.seekTime}`)
            .join(','),
        200
    );

    useEffect(() => {
        if (!pending) {
            return;
        }
        const frames = pending
            .split(',')
            .map(p => {
                const [frame, time] = p.split(':');

                return {frame: Number(frame), time: Number(time)};
            })
            .filter(f => !cache.has(f.frame));
        if (frames.length === 0) {
            return;
        }
        if (!videoRef.current) {
            videoRef.current = createFrameVideo(src);
        }
        const controller = new AbortController();

        void extractVideoFrames(
            videoRef.current,
            frames.map(f => f.time),
            (i, video) => {
                const frame = captureFrame(video);
                if (!frame) {
                    return;
                }
                cache.set(frames[i].frame, frame);
                const canvas = canvases.current.get(frames[i].frame);
                if (canvas) {
                    canvas.dataset.drawn = '1';
                    drawCachedFrame(canvas, frame);
                }
            },
            controller.signal
        );

        return () => controller.abort();
    }, [src, cache, pending]);

    return (
        <div
            ref={ref}
            className={cn('relative overflow-hidden bg-muted', className)}
            aria-hidden
            data-testid="clip-filmstrip"
        >
            {slots.map(slot => (
                <canvas
                    key={slot.frame}
                    ref={el => {
                        if (!el) {
                            return;
                        }
                        canvases.current.set(slot.frame, el);
                        const cached = cache.get(slot.frame);
                        if (cached && el.dataset.drawn !== '1') {
                            el.dataset.drawn = '1';
                            drawCachedFrame(el, cached);
                        }

                        return () => {
                            if (canvases.current.get(slot.frame) === el) {
                                canvases.current.delete(slot.frame);
                            }
                        };
                    }}
                    className="absolute inset-y-0 h-full"
                    style={{
                        left: `${((slot.start - view.start) / view.span) * 100}%`,
                        width: `${sliceWidth}%`,
                    }}
                />
            ))}
        </div>
    );
}
