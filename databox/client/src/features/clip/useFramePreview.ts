'use client';

import {useCallback, useEffect, useRef, useState} from 'react';
import {FRAME_TIMEOUT} from './filmstrip';
import {
    captureFrame,
    createFrameVideo,
    frameSeekTime,
    releaseVideo,
    type VideoFrameCache,
} from './frameCanvas';
import {frameIndex} from './timeline';

const HAVE_METADATA = 1;
const HAVE_CURRENT_DATA = 2;

export type PreviewFrame = {
    /** Frame number */
    frame: number;
    canvas: HTMLCanvasElement;
};

type FramePreviewer = {
    /** The frame at the time is wanted; undefined when no more */
    request: (time: number | undefined) => void;
    dispose: () => void;
};

/**
 * Takes the frames of a video one at a time, the last request winning: the
 * frame wanted while a seek runs replaces the one waiting before it. The
 * video element is created on the first request.
 */
function createFramePreviewer(
    src: string,
    cache: VideoFrameCache,
    frameDuration: number,
    duration: number,
    onFrame: (frame: PreviewFrame) => void
): FramePreviewer {
    let video: HTMLVideoElement | undefined;
    /** Frame waiting for the current seek to end */
    let next: number | undefined;
    /** Frame being sought */
    let seeking: number | undefined;
    let wanted: number | undefined;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const show = (frame: number, canvas: HTMLCanvasElement) => {
        if (wanted === frame) {
            onFrame({frame, canvas});
        }
    };

    const onSeeked = () => {
        clearTimeout(timer);
        const sought = seeking;
        seeking = undefined;
        if (
            sought !== undefined &&
            video &&
            video.readyState >= HAVE_CURRENT_DATA
        ) {
            const canvas = captureFrame(video);
            if (canvas) {
                cache.set(sought, canvas);
                show(sought, canvas);
            }
        }
        seekNext();
    };

    const onError = () => {
        clearTimeout(timer);
        seeking = undefined;
    };

    function seekNext() {
        const frame = next;
        if (seeking !== undefined || frame === undefined) {
            return;
        }
        const cached = cache.get(frame);
        if (cached) {
            next = undefined;
            show(frame, cached);

            return;
        }
        if (!video) {
            video = createFrameVideo(src);
            video.addEventListener('seeked', onSeeked);
            video.addEventListener('error', onError);
            // Seeking waits for the metadata
            video.addEventListener('loadedmetadata', seekNext);
        }
        if (video.readyState < HAVE_METADATA) {
            return;
        }
        next = undefined;
        seeking = frame;
        timer = setTimeout(() => {
            seeking = undefined;
            seekNext();
        }, FRAME_TIMEOUT);
        video.currentTime = frameSeekTime(frame, frameDuration, duration);
    }

    return {
        request: time => {
            if (time === undefined) {
                wanted = undefined;
                next = undefined;

                return;
            }
            const frame = frameIndex(time, frameDuration);
            if (wanted === frame) {
                return;
            }
            wanted = frame;
            next = frame;
            seekNext();
        },
        dispose: () => {
            clearTimeout(timer);
            wanted = undefined;
            next = undefined;
            if (video) {
                video.removeEventListener('seeked', onSeeked);
                video.removeEventListener('error', onError);
                video.removeEventListener('loadedmetadata', seekNext);
                releaseVideo(video);
                video = undefined;
            }
        },
    };
}

/**
 * The frame of a video under the cursor of the timeline, taken by a hidden
 * video element of its own, one seek at a time, the last request winning
 * (see `createFramePreviewer`). The frames go to the cache shared with the
 * filmstrip, and are taken from it when already there.
 */
export function useFramePreview({
    src,
    cache,
    frameDuration,
    duration,
}: {
    src: string | undefined;
    cache: VideoFrameCache | undefined;
    frameDuration: number;
    duration: number;
}): {
    /** The last frame taken, maybe still not the one requested */
    preview: PreviewFrame | undefined;
    /** The frame at the time is wanted; undefined when no more */
    request: (time: number | undefined) => void;
} {
    const [preview, setPreview] = useState<PreviewFrame>();
    const previewer = useRef<FramePreviewer | undefined>(undefined);

    useEffect(() => {
        if (!src || !cache || !(frameDuration > 0) || !(duration > 0)) {
            return;
        }
        const p = createFramePreviewer(
            src,
            cache,
            frameDuration,
            duration,
            setPreview
        );
        previewer.current = p;

        return () => {
            p.dispose();
            if (previewer.current === p) {
                previewer.current = undefined;
            }
        };
    }, [src, cache, frameDuration, duration]);

    const request = useCallback((time: number | undefined) => {
        previewer.current?.request(time);
    }, []);

    return {preview, request};
}
