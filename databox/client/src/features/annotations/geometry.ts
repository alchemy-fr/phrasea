import type {Point} from './types';

/** A rectangle in pixels of the rendered media */
export type Box = {x: number; y: number; w: number; h: number};

/** Size of the rendered media, in CSS pixels (before the player's zoom) */
export type MediaSize = {width: number; height: number};

export const emptyMedia: MediaSize = {width: 0, height: 0};

/** A box drawn backwards (negative width or height) put straight */
export function normalizeBox({x, y, w, h}: Box): Box {
    return {
        x: w >= 0 ? x : x + w,
        y: h >= 0 ? y : y + h,
        w: Math.abs(w),
        h: Math.abs(h),
    };
}

export function boxFromPoints(a: Point, b: Point): Box {
    return normalizeBox({x: a.x, y: a.y, w: b.x - a.x, h: b.y - a.y});
}

export function pointsBox(points: Point[]): Box {
    if (points.length === 0) {
        return {x: 0, y: 0, w: 0, h: 0};
    }
    let minX = Infinity;
    let minY = Infinity;
    let maxX = -Infinity;
    let maxY = -Infinity;
    for (const p of points) {
        minX = Math.min(minX, p.x);
        minY = Math.min(minY, p.y);
        maxX = Math.max(maxX, p.x);
        maxY = Math.max(maxY, p.y);
    }

    return {x: minX, y: minY, w: maxX - minX, h: maxY - minY};
}

export function isPointInBox(p: Point, {x, y, w, h}: Box): boolean {
    return p.x >= x && p.x <= x + w && p.y >= y && p.y <= y + h;
}

export function distance(a: Point, b: Point): number {
    return Math.hypot(b.x - a.x, b.y - a.y);
}

export function clamp(n: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, n));
}

/** Relative (0..1) coordinates <-> pixels of the media */
export function toPixels({width, height}: MediaSize, p: Point): Point {
    return {x: p.x * width, y: p.y * height};
}

export function toRelative({width, height}: MediaSize, p: Point): Point {
    return {x: p.x / width, y: p.y / height};
}

/** Font of the text shapes, the same for the SVG and the measure */
export const textFontFamily = 'sans-serif';

/** Part of the font size above the baseline (ascent) for a sans-serif font */
export const textAscent = 0.8;

/** Distance between the baselines of a text, relative to the font size */
export const textLineHeight = 1.2;

export function textLines(text: string): string[] {
    return text.split('\n');
}

let measureContext: CanvasRenderingContext2D | null | undefined;

/** Width of a text drawn at the given font size, in pixels */
export function measureTextWidth(text: string, fontSize: number): number {
    if (measureContext === undefined) {
        measureContext =
            typeof document === 'undefined'
                ? null
                : document.createElement('canvas').getContext('2d');
    }
    if (!measureContext) {
        return text.length * fontSize * 0.6;
    }
    measureContext.font = `${fontSize}px ${textFontFamily}`;

    return measureContext.measureText(text).width;
}
