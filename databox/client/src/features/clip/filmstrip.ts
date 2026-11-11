/** Most thumbnails extracted for a filmstrip, whatever its width */
export const MAX_FILMSTRIP_FRAMES = 60;

/** Time given to a seek before the frame is skipped, in milliseconds */
export const FRAME_TIMEOUT = 5000;

const DEFAULT_ASPECT = 16 / 9;

/**
 * Number of thumbnails filling a strip of `width` × `height` px, each as wide
 * as `height` × the aspect ratio of the video: the last one may be cut. Never
 * more than `maxFrames`, the frames of the part shown.
 */
export function getFilmstripCount(
    width: number,
    height: number,
    aspect?: number,
    maxFrames?: number
): number {
    const ratio = aspect && aspect > 0 ? aspect : DEFAULT_ASPECT;
    const frameWidth = height * ratio;
    if (!(width > 0) || !(frameWidth > 0)) {
        return 1;
    }
    const max =
        maxFrames !== undefined && maxFrames >= 1
            ? Math.min(MAX_FILMSTRIP_FRAMES, Math.floor(maxFrames))
            : MAX_FILMSTRIP_FRAMES;

    return Math.min(max, Math.max(1, Math.ceil(width / frameWidth)));
}

/**
 * Instant of each thumbnail of the window [`start`, `end`] of the media: the
 * middle of its slice of the window, kept before the end of the media
 * (seeking to the very end may never answer).
 */
export function getFilmstripTimes(
    start: number,
    end: number,
    count: number,
    duration: number
): number[] {
    if (!(duration > 0) || !(end > start) || count < 1) {
        return [];
    }
    const last = Math.max(0, duration - 0.05);
    const slice = (end - start) / count;

    return Array.from({length: count}, (_, i) =>
        Math.max(0, Math.min(last, start + (i + 0.5) * slice))
    );
}

export type FilmstripSlot = {
    /** Start of the slice of the media the thumbnail stands for, in seconds */
    start: number;
    /** Instant shown by the thumbnail */
    time: number;
};

/**
 * Thumbnails covering a view of the media, `count` per width of the view.
 * Their slices are aligned on multiples of their length, not on the start
 * of the view: scrolling keeps the same instants, only the ones coming into
 * view are new.
 */
export function getFilmstripSlots(
    view: {start: number; span: number},
    count: number,
    duration: number
): FilmstripSlot[] {
    if (!(duration > 0) || !(view.span > 0) || count < 1) {
        return [];
    }
    const slice = view.span / count;
    const first = Math.max(0, Math.floor(view.start / slice + 1e-9));
    const end = Math.min(duration, view.start + view.span);
    const n = Math.max(1, Math.ceil(end / slice - 1e-9) - first);
    const times = getFilmstripTimes(
        first * slice,
        (first + n) * slice,
        n,
        duration
    );

    return times.map((time, i) => ({start: (first + i) * slice, time}));
}

/**
 * The last frames drawn, by key (their frame number): the oldest one goes
 * first once `capacity` is reached.
 */
export class FrameCache<T> {
    private readonly entries = new Map<number, T>();

    constructor(private readonly capacity = 300) {}

    get size(): number {
        return this.entries.size;
    }

    get(key: number): T | undefined {
        const value = this.entries.get(key);
        if (value !== undefined) {
            // Most recently used last
            this.entries.delete(key);
            this.entries.set(key, value);
        }

        return value;
    }

    has(key: number): boolean {
        return this.entries.has(key);
    }

    set(key: number, value: T): void {
        this.entries.delete(key);
        this.entries.set(key, value);
        while (this.entries.size > this.capacity) {
            const oldest = this.entries.keys().next().value as number;
            this.entries.delete(oldest);
        }
    }
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
