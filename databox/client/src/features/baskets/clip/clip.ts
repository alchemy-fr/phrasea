import type {CSSProperties} from 'react';
import {FileFamily, getFileFamily} from '@/lib/utils/mime';
import {formatDuration} from '@/lib/utils/format';
import type {
    BasketClip,
    ClipCapabilities,
    ClipCrop,
    ClipTimeRange,
} from './types';

/**
 * Same rules as the API (`ClipFamily`): a time range applies to videos and
 * audios, a crop to images (not SVG) and videos.
 */
export function getClipCapabilities(
    mimeType: string | undefined
): ClipCapabilities {
    const family = getFileFamily(mimeType);
    const svg =
        mimeType?.split(';')[0].trim().toLowerCase() === 'image/svg+xml';

    return {
        timeRange: family === FileFamily.Video || family === FileFamily.Audio,
        crop:
            family === FileFamily.Video ||
            (family === FileFamily.Image && !svg),
    };
}

export function isClipApplicable(mimeType: string | undefined): boolean {
    const {timeRange, crop} = getClipCapabilities(mimeType);

    return timeRange || crop;
}

/**
 * The part of the clip that applies to a file of the given type (the image
 * thumbnail of a video only gets the crop).
 */
export function clipForFile(
    clip: BasketClip | null | undefined,
    mimeType: string | undefined
): BasketClip | undefined {
    if (!clip) {
        return undefined;
    }
    const capabilities = getClipCapabilities(mimeType);
    const applied: BasketClip = {};
    if (capabilities.timeRange && clip.timeRange) {
        applied.timeRange = clip.timeRange;
    }
    if (capabilities.crop && clip.crop) {
        applied.crop = clip.crop;
    }

    return applied.timeRange || applied.crop ? applied : undefined;
}

function formatTime(seconds: number): string {
    const formatted = formatDuration(seconds);

    return formatted.startsWith('00:') ? formatted.slice(3) : formatted;
}

/** `00:12–00:30` */
export function formatClipRange({s, e}: ClipTimeRange): string {
    return `${formatTime(s)}–${formatTime(e)}`;
}

/** `mm:ss.t`, the format of the time fields */
export function formatClipTime(seconds: number): string {
    const tenths = Math.round(Math.max(0, seconds) * 10);
    const minutes = Math.floor(tenths / 600);
    const rest = (tenths % 600) / 10;

    return `${String(minutes).padStart(2, '0')}:${rest.toFixed(1).padStart(4, '0')}`;
}

/**
 * Parses `ss`, `ss.t`, `mm:ss.t` or `hh:mm:ss.t`; undefined when invalid.
 */
export function parseClipTime(value: string): number | undefined {
    const parts = value.trim().split(':');
    if (parts.length === 0 || parts.length > 3 || parts.some(p => p === '')) {
        return undefined;
    }
    let seconds = 0;
    for (const [i, part] of parts.entries()) {
        const isLast = i === parts.length - 1;
        if (!(isLast ? /^\d+(\.\d+)?$/ : /^\d+$/).test(part)) {
            return undefined;
        }
        seconds = seconds * 60 + parseFloat(part);
    }

    return seconds;
}

export function clampToRange(time: number, range: ClipTimeRange): number {
    return Math.min(range.e, Math.max(range.s, time));
}

export function isInRange(time: number, range: ClipTimeRange): boolean {
    return time >= range.s && time < range.e;
}

const round = (n: number, digits: number) => {
    const f = 10 ** digits;

    return Math.round(n * f) / f;
};

/**
 * The clip as sent to the API: values rounded and bounded, empty parts
 * dropped; null when nothing is left.
 */
export function normalizeClip(
    clip: BasketClip | null | undefined,
    duration?: number
): BasketClip | null {
    if (!clip) {
        return null;
    }
    const normalized: BasketClip = {};

    if (clip.timeRange) {
        const s = round(Math.max(0, clip.timeRange.s), 2);
        let e = round(clip.timeRange.e, 2);
        if (duration !== undefined && duration > 0) {
            e = Math.min(e, round(duration, 2));
        }
        if (e > s) {
            normalized.timeRange = {s, e};
        }
    }

    if (clip.crop) {
        const x = round(Math.min(1, Math.max(0, clip.crop.x)), 4);
        const y = round(Math.min(1, Math.max(0, clip.crop.y)), 4);
        const w = round(Math.min(1 - x, Math.max(0, clip.crop.w)), 4);
        const h = round(Math.min(1 - y, Math.max(0, clip.crop.h)), 4);
        const whole = x === 0 && y === 0 && w === 1 && h === 1;
        if (w > 0 && h > 0 && !whole) {
            normalized.crop = {x, y, w, h};
        }
    }

    return normalized.timeRange || normalized.crop ? normalized : null;
}

export function isSameClip(
    a: BasketClip | null | undefined,
    b: BasketClip | null | undefined
): boolean {
    return (
        JSON.stringify(normalizeClip(a)) === JSON.stringify(normalizeClip(b))
    );
}

/**
 * Position of the media inside a frame showing only its crop: the frame has
 * the crop ratio (see `cropAspect`) and hides the overflow.
 */
export function cropFrameStyle(crop: ClipCrop): CSSProperties {
    return {
        position: 'absolute',
        left: `${(-crop.x / crop.w) * 100}%`,
        top: `${(-crop.y / crop.h) * 100}%`,
        width: `${100 / crop.w}%`,
        height: `${100 / crop.h}%`,
        maxWidth: 'none',
        maxHeight: 'none',
    };
}

/** Width / height of the cropped part of a media of the given ratio */
export function cropAspect(crop: ClipCrop, mediaAspect: number): number {
    return (crop.w / crop.h) * mediaAspect;
}
