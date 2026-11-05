/** Most thumbnails extracted for a filmstrip, whatever its width */
export const MAX_FILMSTRIP_FRAMES = 60;

/** Time given to a seek before the frame is skipped, in milliseconds */
export const FRAME_TIMEOUT = 5000;

const DEFAULT_ASPECT = 16 / 9;

/**
 * Number of thumbnails filling a strip of `width` × `height` px, each as wide
 * as `height` × the aspect ratio of the video: the last one may be cut.
 */
export function getFilmstripCount(
    width: number,
    height: number,
    aspect?: number
): number {
    const ratio = aspect && aspect > 0 ? aspect : DEFAULT_ASPECT;
    const frameWidth = height * ratio;
    if (!(width > 0) || !(frameWidth > 0)) {
        return 1;
    }

    return Math.min(
        MAX_FILMSTRIP_FRAMES,
        Math.max(1, Math.ceil(width / frameWidth))
    );
}

/**
 * Instant of each thumbnail: the middle of its slice of the duration, kept
 * before the end (seeking to the very end may never answer).
 */
export function getFilmstripTimes(duration: number, count: number): number[] {
    if (!(duration > 0) || count < 1) {
        return [];
    }
    const last = Math.max(0, duration - 0.05);

    return Array.from({length: count}, (_, i) =>
        Math.min(last, ((i + 0.5) * duration) / count)
    );
}

/** What `extractVideoFrames` needs of a video element */
export type FrameSource = EventTarget & {
    readonly readyState: number;
    currentTime: number;
};

const HAVE_CURRENT_DATA = 2;

/**
 * Resolves with the first of `events` to fire, `timeout` after `timeout` ms,
 * or `abort`; the listeners are removed in every case.
 */
function waitFor(
    target: FrameSource,
    events: string[],
    signal: AbortSignal,
    timeout?: number
): Promise<string> {
    return new Promise(resolve => {
        if (signal.aborted) {
            resolve('abort');

            return;
        }
        let timer: ReturnType<typeof setTimeout> | undefined;
        const done = (reason: string) => {
            events.forEach(e => target.removeEventListener(e, onEvent));
            signal.removeEventListener('abort', onAbort);
            clearTimeout(timer);
            resolve(reason);
        };
        const onEvent = (e: Event) => done(e.type);
        const onAbort = () => done('abort');
        events.forEach(e => target.addEventListener(e, onEvent));
        signal.addEventListener('abort', onAbort);
        if (timeout !== undefined) {
            timer = setTimeout(() => done('timeout'), timeout);
        }
    });
}

/**
 * Seeks `video` to each of `times` in turn, one seek at a time, and calls
 * `onFrame` once the frame is shown, to draw it. A frame which fails to
 * load, or takes more than `FRAME_TIMEOUT` ms, is skipped. Stops as soon as
 * `signal` is aborted.
 */
export async function extractVideoFrames<V extends FrameSource>(
    video: V,
    times: number[],
    onFrame: (index: number, video: V) => void,
    signal: AbortSignal
): Promise<void> {
    if (signal.aborted) {
        return;
    }
    if (video.readyState < HAVE_CURRENT_DATA) {
        const reason = await waitFor(video, ['loadeddata', 'error'], signal);
        if (reason !== 'loadeddata') {
            return;
        }
    }

    for (let i = 0; i < times.length && !signal.aborted; i++) {
        // Listening before seeking: the event may fire right away
        const seeked = waitFor(
            video,
            ['seeked', 'error'],
            signal,
            FRAME_TIMEOUT
        );
        video.currentTime = times[i];
        const reason = await seeked;
        if (reason === 'abort' || signal.aborted) {
            return;
        }
        if (reason === 'seeked') {
            onFrame(i, video);
        }
    }
}
