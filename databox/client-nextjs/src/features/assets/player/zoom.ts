import {clamp} from '@/lib/utils/misc';

/**
 * Zoom levels the buttons step through, as a ratio of the natural size of
 * the image (1 = one image pixel per screen pixel). The level fitting the
 * image to the viewport is inserted among them.
 */
export const ZOOM_STEPS = [
    0.1, 0.25, 0.5, 0.75, 1, 1.5, 2, 3, 4, 6, 8, 12, 16,
] as const;

export const MAX_ZOOM = ZOOM_STEPS[ZOOM_STEPS.length - 1];

/**
 * Sizes the view depends on: the viewport, the image as laid out (fitted by
 * CSS, before any transform) and its natural width.
 */
export type ZoomMetrics = {
    /** Viewport */
    cw: number;
    ch: number;
    /** Image laid out, untransformed */
    fw: number;
    fh: number;
    /** Natural width of the image */
    nw: number;
};

/**
 * `scale` applies to the laid out image, `x`/`y` move its center away from
 * the center of the viewport, `rotation` is in degrees (quarter turns).
 */
export type ZoomView = {
    scale: number;
    x: number;
    y: number;
    rotation: number;
};

export const initialView: ZoomView = {scale: 1, x: 0, y: 0, rotation: 0};

function isQuarterTurn(rotation: number): boolean {
    return Math.abs(rotation) % 180 === 90;
}

/** Size of the image once scaled and rotated */
function boxSize(m: ZoomMetrics, v: Pick<ZoomView, 'scale' | 'rotation'>) {
    const quarter = isQuarterTurn(v.rotation);

    return {
        w: (quarter ? m.fh : m.fw) * v.scale,
        h: (quarter ? m.fw : m.fh) * v.scale,
    };
}

/** Zoom level (natural size ratio) of a scale */
export function toZoom(m: ZoomMetrics, scale: number): number {
    return (scale * m.fw) / m.nw;
}

export function toScale(m: ZoomMetrics, zoom: number): number {
    return (zoom * m.nw) / m.fw;
}

/**
 * The scale fitting the rotated image in the viewport, never beyond its
 * natural size: 1 without rotation, the CSS layout already fits it.
 */
export function fitScale(m: ZoomMetrics, rotation: number): number {
    const {w, h} = boxSize(m, {scale: 1, rotation});

    return Math.min(m.cw / w, m.ch / h, m.nw / m.fw);
}

/**
 * Keeps the image on screen: a side larger than the viewport pans until its
 * edge, a smaller one stays centered.
 */
export function clampOffset(m: ZoomMetrics, v: ZoomView): ZoomView {
    const {w, h} = boxSize(m, v);
    const mx = Math.max(0, (w - m.cw) / 2);
    const my = Math.max(0, (h - m.ch) / 2);

    return {...v, x: clamp(v.x, -mx, mx), y: clamp(v.y, -my, my)};
}

/**
 * Scales the view, the point `origin` (relative to the viewport center)
 * staying under the pointer. Bounded by the fitted scale and
 * {@link MAX_ZOOM}.
 */
export function zoomView(
    m: ZoomMetrics,
    v: ZoomView,
    scale: number,
    origin = {x: 0, y: 0}
): ZoomView {
    const fit = fitScale(m, v.rotation);
    const next = clamp(scale, fit, Math.max(fit, toScale(m, MAX_ZOOM)));
    const k = next / v.scale;

    return clampOffset(m, {
        ...v,
        scale: next,
        x: origin.x - (origin.x - v.x) * k,
        y: origin.y - (origin.y - v.y) * k,
    });
}

/**
 * The next zoom level of {@link ZOOM_STEPS} (fit included) in a direction,
 * undefined at the end of the range.
 */
export function nextZoomStep(
    current: number,
    fit: number,
    direction: 1 | -1
): number | undefined {
    const stops = [...ZOOM_STEPS, fit]
        .filter(s => s >= fit - 1e-6)
        .sort((a, b) => a - b);
    // Tolerates the rounding of a level reached by the wheel
    const epsilon = 1.01;

    return direction > 0
        ? stops.find(s => s > current * epsilon)
        : stops.reverse().find(s => s < current / epsilon);
}

/** Zoom factor of a wheel event: proportional to its delta */
export function wheelFactor(e: {
    deltaY: number;
    deltaMode: number;
    ctrlKey: boolean;
}): number {
    // Lines (Firefox mouse wheel) and pages to pixels
    const unit = e.deltaMode === 1 ? 16 : e.deltaMode === 2 ? 800 : 1;
    // A trackpad pinch comes as a ctrl + wheel with small deltas
    const speed = e.ctrlKey ? 0.01 : 0.002;

    return Math.exp(-clamp(e.deltaY * unit, -100, 100) * speed);
}
