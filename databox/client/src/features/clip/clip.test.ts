import {describe, expect, it} from 'vitest';
import {
    clampToRange,
    clipForFile,
    clipPlaybackTarget,
    cropAspect,
    cropFrameStyle,
    effectiveClip,
    formatClipRange,
    formatClipRanges,
    formatClipTime,
    getClipCapabilities,
    isClipApplicable,
    isSameClip,
    MAX_TIME_RANGES,
    normalizeClip,
    normalizeTimeRanges,
    parseClipTime,
    rangeIndexAt,
} from './clip';
import {constrainedBox, fitCropToRatio} from './CropRectEditor';

const crop = {x: 0.1, y: 0.2, w: 0.5, h: 0.25};
const timeRange = {s: 12, e: 30.5};
const timeRanges = [timeRange];

describe('clip capabilities', () => {
    it('follows the API rules', () => {
        expect(getClipCapabilities('video/mp4')).toEqual({
            timeRange: true,
            crop: true,
        });
        expect(getClipCapabilities('audio/mpeg')).toEqual({
            timeRange: true,
            crop: false,
        });
        expect(getClipCapabilities('image/jpeg')).toEqual({
            timeRange: false,
            crop: true,
        });
        expect(getClipCapabilities('image/svg+xml')).toEqual({
            timeRange: false,
            crop: false,
        });
        expect(isClipApplicable('application/pdf')).toBe(false);
        expect(isClipApplicable(undefined)).toBe(false);
        expect(isClipApplicable('application/ogg')).toBe(true);
    });

    it('keeps the parts that apply to a file', () => {
        const clip = {timeRanges, crop};
        expect(clipForFile(clip, 'video/mp4')).toEqual(clip);
        expect(clipForFile(clip, 'audio/mpeg')).toEqual({timeRanges});
        // The image thumbnail of a video
        expect(clipForFile(clip, 'image/jpeg')).toEqual({crop});
        expect(clipForFile({timeRanges}, 'image/jpeg')).toBeUndefined();
        expect(clipForFile({timeRanges: []}, 'video/mp4')).toBeUndefined();
        expect(clipForFile(clip, 'application/pdf')).toBeUndefined();
        expect(clipForFile(undefined, 'video/mp4')).toBeUndefined();
        expect(clipForFile(null, 'video/mp4')).toBeUndefined();
    });
});

describe('effective clip', () => {
    it('prefers the extract of the basket item to the region of interest', () => {
        const roi = {crop};
        const basketClip = {timeRanges};
        expect(effectiveClip({roi})).toBe(roi);
        expect(effectiveClip({roi, basketClip})).toBe(basketClip);
        expect(effectiveClip({basketClip})).toBe(basketClip);
        expect(effectiveClip({roi: null})).toBeUndefined();
        expect(effectiveClip({})).toBeUndefined();
    });
});

describe('clip times', () => {
    it('formats the range', () => {
        expect(formatClipRange(timeRange)).toBe('00:12–00:31');
        expect(formatClipRange({s: 3600, e: 3725})).toBe('01:00:00–01:02:05');
    });

    it('formats several ranges', () => {
        expect(formatClipRanges([])).toBe('');
        expect(formatClipRanges(timeRanges)).toBe('00:12–00:31');
        expect(
            formatClipRanges([timeRange, {s: 40, e: 50}, {s: 60, e: 70}])
        ).toBe('00:12–00:31 +2');
    });

    it('finds where the playback of the ranges goes', () => {
        const ranges = [
            {s: 2, e: 4},
            {s: 10, e: 12},
        ];
        expect(rangeIndexAt(3, ranges)).toBe(0);
        expect(rangeIndexAt(4, ranges)).toBe(-1);
        expect(rangeIndexAt(11, ranges)).toBe(1);

        expect(clipPlaybackTarget(3, ranges)).toBeUndefined();
        expect(clipPlaybackTarget(0, ranges)).toEqual({time: 2, ended: false});
        expect(clipPlaybackTarget(4, ranges)).toEqual({
            time: 10,
            ended: false,
        });
        expect(clipPlaybackTarget(12, ranges)).toEqual({time: 2, ended: true});
        expect(clipPlaybackTarget(5, [])).toBeUndefined();
    });

    it('formats and parses the time fields', () => {
        expect(formatClipTime(72.54)).toBe('01:12.5');
        expect(formatClipTime(0)).toBe('00:00.0');
        expect(formatClipTime(5)).toBe('00:05.0');
        expect(formatClipTime(59.96)).toBe('01:00.0');

        expect(parseClipTime('01:12.5')).toBe(72.5);
        expect(parseClipTime('12')).toBe(12);
        expect(parseClipTime(' 1:00:01 ')).toBe(3601);
        expect(parseClipTime('3.25')).toBe(3.25);
        expect(parseClipTime('')).toBeUndefined();
        expect(parseClipTime('1:')).toBeUndefined();
        expect(parseClipTime('a:10')).toBeUndefined();
        expect(parseClipTime('1.5:10')).toBeUndefined();
        expect(parseClipTime('1:2:3:4')).toBeUndefined();
    });

    it('clamps a time to the range', () => {
        expect(clampToRange(3, timeRange)).toBe(12);
        expect(clampToRange(20, timeRange)).toBe(20);
        expect(clampToRange(40, timeRange)).toBe(30.5);
    });
});

describe('normalizeClip', () => {
    it('drops the empty clips', () => {
        expect(normalizeClip(null)).toBeNull();
        expect(normalizeClip({})).toBeNull();
        expect(normalizeClip({timeRanges: [{s: 5, e: 5}]})).toBeNull();
        expect(normalizeClip({timeRanges: []})).toBeNull();
        expect(normalizeClip({crop: {x: 0, y: 0, w: 1, h: 1}})).toBeNull();
        expect(
            normalizeClip({crop: {x: 0.2, y: 0.2, w: 0, h: 0.5}})
        ).toBeNull();
    });

    it('rounds and bounds the values', () => {
        expect(
            normalizeClip(
                {
                    timeRanges: [{s: -1, e: 99.999}],
                    crop: {x: 0.123456, y: 0.5, w: 0.9, h: 0.6},
                },
                60
            )
        ).toEqual({
            timeRanges: [{s: 0, e: 60}],
            crop: {x: 0.1235, y: 0.5, w: 0.8765, h: 0.5},
        });
    });

    it('sorts and merges the ranges', () => {
        expect(
            normalizeTimeRanges([
                {s: 50, e: 60},
                {s: 10, e: 20},
                {s: 15, e: 25},
                {s: 25, e: 30},
                {s: 52, e: 55},
                {s: 40, e: 40},
                {s: 70, e: 90},
            ])
        ).toEqual([
            {s: 10, e: 30},
            {s: 50, e: 60},
            {s: 70, e: 90},
        ]);
        // Bounded to the duration
        expect(
            normalizeTimeRanges(
                [
                    {s: 5, e: 8},
                    {s: 70, e: 90},
                ],
                60
            )
        ).toEqual([{s: 5, e: 8}]);
        const many = Array.from({length: MAX_TIME_RANGES + 5}, (_, i) => ({
            s: i * 10,
            e: i * 10 + 1,
        }));
        expect(normalizeTimeRanges(many)).toHaveLength(MAX_TIME_RANGES);
    });

    it('compares clips by value', () => {
        expect(
            isSameClip(
                {timeRanges: [{s: 1, e: 2.0001}]},
                {timeRanges: [{s: 1, e: 2}]}
            )
        ).toBe(true);
        expect(
            isSameClip(
                {
                    timeRanges: [
                        {s: 5, e: 6},
                        {s: 1, e: 2},
                    ],
                },
                {
                    timeRanges: [
                        {s: 1, e: 2},
                        {s: 5, e: 6},
                    ],
                }
            )
        ).toBe(true);
        expect(isSameClip({timeRanges: [{s: 1, e: 2}]}, null)).toBe(false);
        expect(isSameClip({}, null)).toBe(true);
    });
});

describe('crop display', () => {
    it('positions the media in the frame of the crop', () => {
        expect(
            cropFrameStyle({x: 0.25, y: 0.5, w: 0.5, h: 0.25})
        ).toMatchObject({
            position: 'absolute',
            left: '-50%',
            top: '-200%',
            width: '200%',
            height: '400%',
        });
    });

    it('computes the ratio of the cropped part', () => {
        // Half the width and half the height of a 16:9 media: still 16:9
        expect(cropAspect({x: 0, y: 0, w: 0.5, h: 0.5}, 16 / 9)).toBeCloseTo(
            16 / 9
        );
        expect(cropAspect({x: 0, y: 0, w: 0.5, h: 1}, 2)).toBe(1);
    });
});

describe('crop editor geometry', () => {
    it('draws free or constrained rectangles', () => {
        // Free, drawn backwards
        const free = constrainedBox(
            {x: 0.6, y: 0.6},
            {x: 0.2, y: 0.4},
            undefined,
            1
        );
        expect(free.x).toBeCloseTo(0.2);
        expect(free.y).toBeCloseTo(0.4);
        expect(free.w).toBeCloseTo(0.4);
        expect(free.h).toBeCloseTo(0.2);

        // Square on a 2:1 media: the relative height is twice the width
        const square = constrainedBox({x: 0, y: 0}, {x: 0.5, y: 0.5}, 1, 2);
        expect(square.w).toBeCloseTo(0.25);
        expect(square.h).toBeCloseTo(0.5);
    });

    it('fits a crop to a ratio, within the media', () => {
        // The largest centered square of a 16:9 media
        const fitted = fitCropToRatio(undefined, 1, 16 / 9);
        expect(fitted.h).toBeCloseTo(1);
        expect(fitted.w).toBeCloseTo(9 / 16);
        expect(fitted.x).toBeCloseTo((1 - 9 / 16) / 2);
        expect(fitted.y).toBeCloseTo(0);

        // Around the center of the current crop
        const refit = fitCropToRatio({x: 0.1, y: 0.1, w: 0.2, h: 0.2}, 2, 1);
        expect(refit.w / refit.h).toBeCloseTo(2);
        expect(refit.x + refit.w / 2).toBeCloseTo(0.2);
        expect(refit.y + refit.h / 2).toBeCloseTo(0.2);
        for (const v of [
            refit.x,
            refit.y,
            refit.x + refit.w,
            refit.y + refit.h,
        ]) {
            expect(v).toBeGreaterThanOrEqual(0);
            expect(v).toBeLessThanOrEqual(1);
        }
    });
});
