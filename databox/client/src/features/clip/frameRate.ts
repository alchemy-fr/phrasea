/** The usual frame rates, a measure is rounded to the nearest one */
export const STANDARD_FRAME_RATES = [
    23.976, 24, 25, 29.97, 30, 50, 59.94, 60,
] as const;

/** When the frame rate cannot be measured */
export const DEFAULT_FRAME_RATE = 25;

/** Relative gap within which a measure is a standard frame rate */
const STANDARD_TOLERANCE = 0.03;

/** Fewest intervals between frames a measure is made of */
const MIN_INTERVALS = 5;

/** Frames played to measure the frame rate */
const SAMPLE_FRAMES = 24;

/** Time given to the playback once started, then to the loading, in ms */
export const MEASURE_TIMEOUT = 2000;
export const LOAD_TIMEOUT = 10000;

/**
 * Frame rate of a video from the media times of frames shown one after the
 * other: the mean of the intervals close to their median (a frame skipped
 * now and then does not count), rounded to the nearest usual frame rate within 3 %, otherwise to
 * the hundredth. Repeated or backward times are ignored. Undefined with too
 * few intervals.
 */
export function estimateFrameRate(mediaTimes: number[]): number | undefined {
    const intervals: number[] = [];
    for (let i = 1; i < mediaTimes.length; i++) {
        const interval = mediaTimes[i] - mediaTimes[i - 1];
        if (interval > 1e-4 && Number.isFinite(interval)) {
            intervals.push(interval);
        }
    }
    if (intervals.length < MIN_INTERVALS) {
        return undefined;
    }
    intervals.sort((a, b) => a - b);
    const middle = Math.floor(intervals.length / 2);
    const median =
        intervals.length % 2
            ? intervals[middle]
            : (intervals[middle - 1] + intervals[middle]) / 2;
    // The mean of the intervals around the median smooths a jitter
    const near = intervals.filter(i => Math.abs(i - median) <= median * 0.3);
    const fps = near.length / near.reduce((sum, i) => sum + i, 0);

    let nearest: number = STANDARD_FRAME_RATES[0];
    for (const rate of STANDARD_FRAME_RATES) {
        if (Math.abs(rate - fps) < Math.abs(nearest - fps)) {
            nearest = rate;
        }
    }
    if (Math.abs(nearest - fps) / nearest <= STANDARD_TOLERANCE) {
        return nearest;
    }

    return Math.round(fps * 100) / 100;
}

type FrameCallbackVideo = HTMLVideoElement & {
    requestVideoFrameCallback: (
        callback: (
            now: number,
            metadata: {mediaTime: number; presentedFrames: number}
        ) => void
    ) => number;
    cancelVideoFrameCallback: (handle: number) => void;
};

/**
 * Measures the frame rate of a video in the browser: a hidden, muted video
 * element plays its first frames, and the media time of each one shown is
 * noted (`requestVideoFrameCallback`). Undefined when it cannot be measured:
 * no `requestVideoFrameCallback`, playback refused, too slow to load or to
 * play, aborted.
 */
export function measureFrameRate(
    src: string,
    signal: AbortSignal
): Promise<number | undefined> {
    if (
        signal.aborted ||
        typeof document === 'undefined' ||
        typeof HTMLVideoElement === 'undefined' ||
        !('requestVideoFrameCallback' in HTMLVideoElement.prototype)
    ) {
        return Promise.resolve(undefined);
    }
    const video = document.createElement('video') as FrameCallbackVideo;
    video.muted = true;
    video.playsInline = true;
    video.preload = 'auto';
    video.src = src;
    const times: number[] = [];

    return new Promise(resolve => {
        let handle: number | undefined;
        let done = false;
        let timer: ReturnType<typeof setTimeout> | undefined;

        const finish = (failed = false) => {
            if (done) {
                return;
            }
            done = true;
            clearTimeout(timer);
            signal.removeEventListener('abort', onAbort);
            if (handle !== undefined) {
                video.cancelVideoFrameCallback(handle);
            }
            video.pause();
            // Releases the download and the decoder
            video.removeAttribute('src');
            video.load();
            resolve(
                failed || signal.aborted ? undefined : estimateFrameRate(times)
            );
        };
        const onAbort = () => finish(true);
        const onFrame = (_now: number, metadata: {mediaTime: number}) => {
            times.push(metadata.mediaTime);
            if (times.length >= SAMPLE_FRAMES) {
                finish();
            } else {
                handle = video.requestVideoFrameCallback(onFrame);
            }
        };

        timer = setTimeout(() => finish(), LOAD_TIMEOUT);
        signal.addEventListener('abort', onAbort);
        handle = video.requestVideoFrameCallback(onFrame);
        video.play().then(
            () => {
                if (!done) {
                    clearTimeout(timer);
                    timer = setTimeout(() => finish(), MEASURE_TIMEOUT);
                }
            },
            () => finish(true)
        );
    });
}
