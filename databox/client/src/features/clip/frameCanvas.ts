import {FrameCache} from './filmstrip';

/** Height of the frames kept in the cache, in px */
const CAPTURE_HEIGHT = 120;

/** The frames of a video kept to be drawn again, by frame number */
export type VideoFrameCache = FrameCache<HTMLCanvasElement>;

export function createVideoFrameCache(): VideoFrameCache {
    return new FrameCache<HTMLCanvasElement>(300);
}

/**
 * Draws `source` on `canvas`, covering it (like `object-fit: cover`), at the
 * resolution of the screen.
 *
 * A cross-origin video taints the canvas it is drawn on, and every canvas
 * drawn from that one: it is still shown, its pixels just cannot be read
 * back, so a frame is only ever drawn, never turned into a data URL.
 */
export function drawCover(
    canvas: HTMLCanvasElement,
    source: CanvasImageSource,
    sourceWidth: number,
    sourceHeight: number
): void {
    const dpr = window.devicePixelRatio || 1;
    const width = Math.round(canvas.clientWidth * dpr);
    const height = Math.round(canvas.clientHeight * dpr);
    if (!width || !height || !sourceWidth || !sourceHeight) {
        return;
    }
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        return;
    }
    const scale = Math.max(width / sourceWidth, height / sourceHeight);
    const sw = width / scale;
    const sh = height / scale;
    try {
        ctx.drawImage(
            source,
            (sourceWidth - sw) / 2,
            (sourceHeight - sh) / 2,
            sw,
            sh,
            0,
            0,
            width,
            height
        );
    } catch {
        // The canvas stays empty
    }
}

/** Draws a frame of the cache on `canvas`, covering it */
export function drawCachedFrame(
    canvas: HTMLCanvasElement,
    frame: HTMLCanvasElement
): void {
    drawCover(canvas, frame, frame.width, frame.height);
}

/** The frame shown by `video`, on a canvas of its own */
export function captureFrame(
    video: HTMLVideoElement
): HTMLCanvasElement | undefined {
    const {videoWidth, videoHeight} = video;
    if (!videoWidth || !videoHeight) {
        return undefined;
    }
    const height = Math.min(CAPTURE_HEIGHT, videoHeight);
    const width = Math.max(1, Math.round((height * videoWidth) / videoHeight));
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        return undefined;
    }
    try {
        ctx.drawImage(video, 0, 0, width, height);
    } catch {
        return undefined;
    }

    return canvas;
}

/** Creates a hidden, muted video element to take frames from */
export function createFrameVideo(src: string): HTMLVideoElement {
    const video = document.createElement('video');
    video.muted = true;
    video.playsInline = true;
    video.preload = 'auto';
    video.src = src;

    return video;
}

/** Releases the download and the decoder of a video element */
export function releaseVideo(video: HTMLVideoElement): void {
    video.removeAttribute('src');
    video.load();
}

/**
 * Instant a frame is taken at: its middle, a seek to its very start may
 * show the previous one; kept before the end (seeking to the very end may
 * never answer).
 */
export function frameSeekTime(
    frame: number,
    frameDuration: number,
    duration: number
): number {
    return Math.max(
        0,
        Math.min((frame + 0.5) * frameDuration, duration - 0.05)
    );
}
