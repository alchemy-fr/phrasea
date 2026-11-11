/** The part of the media shown by the timeline, in seconds */
export type TimelineView = {
    start: number;
    span: number;
};

/** Width of a frame at the deepest zoom of a video, in px */
export const VIDEO_FRAME_PX = 24;

/**
 * A sound has no frame: its "frame" is the precision of the stored times
 * (0.01 s), about 8 px wide at the deepest zoom.
 */
export const AUDIO_FRAME_DURATION = 0.01;
export const AUDIO_FRAME_PX = 8;

/** Least room between two graduations, and between two labels, in px */
export const MIN_TICK_PX = 8;
export const MIN_LABEL_PX = 80;

const EPSILON = 1e-6;

const clamp = (value: number, min: number, max: number) =>
    Math.min(max, Math.max(min, value));

/** Rounds away the float noise of the frame arithmetic */
const clean = (n: number) => Math.round(n * 1e6) / 1e6;

export function fullView(duration: number): TimelineView {
    return {start: 0, span: Math.max(0, duration)};
}

/**
 * Shortest span of the view: a frame `framePx` wide over the width of the
 * timeline, never less than a frame.
 */
export function getMinSpan(
    widthPx: number,
    frameDuration: number,
    framePx: number = VIDEO_FRAME_PX
): number {
    if (!(widthPx > 0) || !(framePx > 0)) {
        return frameDuration;
    }

    return Math.max(frameDuration, (frameDuration * widthPx) / framePx);
}

/** The view kept within the media, between `minSpan` and the whole duration */
export function clampView(
    view: TimelineView,
    duration: number,
    minSpan: number
): TimelineView {
    if (!(duration > 0)) {
        return {start: 0, span: 0};
    }
    const span = clamp(view.span, Math.min(minSpan, duration), duration);
    const start = clamp(view.start, 0, duration - span);

    return {start, span};
}

/**
 * Zooms in (`factor` > 1) or out (< 1), `anchorTime` staying at the same
 * place of the timeline (under the cursor).
 */
export function zoomView(
    view: TimelineView,
    factor: number,
    anchorTime: number,
    duration: number,
    minSpan: number
): TimelineView {
    if (!(factor > 0) || !(view.span > 0)) {
        return clampView(view, duration, minSpan);
    }
    const anchor = clamp(anchorTime, view.start, view.start + view.span);
    const ratio = (anchor - view.start) / view.span;
    const span = clamp(
        view.span / factor,
        Math.min(minSpan, duration),
        duration
    );

    return clampView({start: anchor - ratio * span, span}, duration, minSpan);
}

/** Scrolls the view by `delta` seconds */
export function panView(
    view: TimelineView,
    delta: number,
    duration: number
): TimelineView {
    return {
        start: clamp(view.start + delta, 0, Math.max(0, duration - view.span)),
        span: view.span,
    };
}

/** The view centered on a time */
export function centerView(
    view: TimelineView,
    time: number,
    duration: number
): TimelineView {
    return panView(view, time - (view.start + view.span / 2), duration);
}

export function isInView(view: TimelineView, time: number): boolean {
    return (
        time >= view.start - EPSILON && time <= view.start + view.span + EPSILON
    );
}

/**
 * The view following the playhead, page by page: past the right edge, the
 * next page starts at it; before the left edge, the previous page ends at
 * it. Unchanged while it is visible.
 */
export function followPlayhead(
    view: TimelineView,
    time: number,
    duration: number
): TimelineView {
    if (isInView(view, time)) {
        return view;
    }
    const start = time > view.start ? time : time - view.span;

    return panView(view, start - view.start, duration);
}

export function timeToX(
    time: number,
    view: TimelineView,
    widthPx: number
): number {
    return view.span > 0 ? ((time - view.start) / view.span) * widthPx : 0;
}

export function xToTime(
    x: number,
    view: TimelineView,
    widthPx: number
): number {
    return widthPx > 0 ? view.start + (x / widthPx) * view.span : view.start;
}

/** Position of a time in the view, in % of its width */
export function timeToPercent(time: number, view: TimelineView): number {
    return view.span > 0 ? ((time - view.start) / view.span) * 100 : 0;
}

/** The nearest frame start */
export function snapToFrame(time: number, frameDuration: number): number {
    if (!(frameDuration > 0)) {
        return time;
    }

    return clean(Math.round(time / frameDuration) * frameDuration);
}

/** Number of the frame shown at a time, from 0 */
export function frameIndex(time: number, frameDuration: number): number {
    if (!(frameDuration > 0)) {
        return 0;
    }

    return Math.max(0, Math.floor(time / frameDuration + EPSILON));
}

/** Step between two graduations: a number of frames, or of seconds */
export type TickStep =
    | {unit: 'frames'; count: number; seconds: number}
    | {unit: 'seconds'; seconds: number};

export type Tick = {time: number; major: boolean};

export type TimelineTicks = {
    minor: TickStep;
    major: TickStep;
    ticks: Tick[];
};

const FRAME_STEPS = [1, 2, 5, 10];
const SECOND_STEPS = [
    0.01, 0.02, 0.05, 0.1, 0.2, 0.5, 1, 2, 5, 10, 15, 30, 60, 120, 300, 600,
    900, 1800, 3600,
];

/**
 * The steps a graduation can take, from the finest: whole frames (for a
 * video) below a tenth of a second, then seconds, minutes and the hour,
 * never finer than a frame.
 */
export function getTickScale(
    frameDuration: number,
    frames: boolean
): TickStep[] {
    const scale: TickStep[] = [];
    if (frames && frameDuration > 0) {
        for (const count of FRAME_STEPS) {
            const seconds = count * frameDuration;
            if (seconds < 0.1 - EPSILON) {
                scale.push({unit: 'frames', count, seconds});
            }
        }
    }
    const finest = frames ? 0.1 : frameDuration;
    for (const seconds of SECOND_STEPS) {
        if (seconds >= finest - EPSILON) {
            scale.push({unit: 'seconds', seconds});
        }
    }

    return scale;
}

const isMultiple = (value: number, step: number) => {
    const n = value / step;

    return Math.abs(n - Math.round(n)) < 1e-4;
};

/**
 * Graduations of the view: the finest step at least `MIN_TICK_PX` apart,
 * and, labelled, the finest multiple of it at least `MIN_LABEL_PX` apart.
 */
export function getTicks(
    view: TimelineView,
    widthPx: number,
    frameDuration: number,
    frames = true
): TimelineTicks {
    const scale = getTickScale(frameDuration, frames);
    const last = scale[scale.length - 1];
    const pxPerSecond = view.span > 0 && widthPx > 0 ? widthPx / view.span : 0;
    const minor =
        scale.find(s => s.seconds * pxPerSecond >= MIN_TICK_PX) ?? last;
    let major = scale.find(
        s =>
            s.seconds >= minor.seconds &&
            s.seconds * pxPerSecond >= MIN_LABEL_PX &&
            isMultiple(s.seconds, minor.seconds)
    );
    if (!major) {
        const factor =
            pxPerSecond > 0
                ? Math.max(
                      1,
                      Math.ceil(MIN_LABEL_PX / (minor.seconds * pxPerSecond))
                  )
                : 1;
        major =
            minor.unit === 'frames'
                ? {
                      unit: 'frames',
                      count: minor.count * factor,
                      seconds: minor.seconds * factor,
                  }
                : {unit: 'seconds', seconds: minor.seconds * factor};
    }

    const ticks: Tick[] = [];
    if (view.span > 0 && minor.seconds > 0) {
        const first = Math.ceil(view.start / minor.seconds - EPSILON);
        const end = view.start + view.span + EPSILON;
        for (let i = first; i * minor.seconds <= end; i++) {
            const time = clean(i * minor.seconds);
            ticks.push({time, major: isMultiple(time, major.seconds)});
        }
    }

    return {minor, major, ticks};
}

/**
 * Label of a graduation: `m:ss` for whole seconds, with the hundredths or
 * tenths below (`m:ss.cc`), `h:mm:ss` past an hour.
 */
export function formatTickLabel(time: number, step: TickStep): string {
    const decimals =
        step.seconds >= 1 - EPSILON ? 0 : isMultiple(step.seconds, 0.1) ? 1 : 2;
    const factor = 10 ** decimals;
    const scaled = Math.round(Math.max(0, time) * factor);
    const whole = Math.floor(scaled / factor);
    const fraction = scaled % factor;
    const hours = Math.floor(whole / 3600);
    const minutes = Math.floor((whole % 3600) / 60);
    const seconds = whole % 60;
    const ss = String(seconds).padStart(2, '0');
    const head =
        hours > 0
            ? `${hours}:${String(minutes).padStart(2, '0')}:${ss}`
            : `${minutes}:${ss}`;

    return decimals > 0
        ? `${head}.${String(fraction).padStart(decimals, '0')}`
        : head;
}

/** A step in seconds, minutes or hours: `0.5 s`, `10 s`, `5 min`, `1 h` */
export function formatStepDuration(seconds: number): string {
    if (seconds >= 3600 - EPSILON) {
        return `${clean(seconds / 3600)} h`;
    }
    if (seconds >= 60 - EPSILON) {
        return `${clean(seconds / 60)} min`;
    }

    return `${clean(seconds)} s`;
}
