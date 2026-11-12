import {describe, expect, it} from 'vitest';
import {
    centerView,
    clampView,
    followPlayhead,
    formatStepDuration,
    formatTickLabel,
    frameIndex,
    fullView,
    getMinSpan,
    getTicks,
    getTickScale,
    panView,
    snapToFrame,
    timeToX,
    xToTime,
    zoomView,
} from './timeline';

const fd25 = 1 / 25;

describe('view', () => {
    it('converts times and pixels', () => {
        const view = {start: 10, span: 20};
        expect(timeToX(15, view, 1000)).toBe(250);
        expect(xToTime(250, view, 1000)).toBe(15);
        expect(xToTime(0, view, 0)).toBe(10);
    });

    it('zooms down to a frame 24 px wide', () => {
        // 1000 px: 1000 / 24 frames
        expect(getMinSpan(1000, fd25)).toBeCloseTo(1000 / 24 / 25);
        expect(getMinSpan(10, fd25)).toBe(fd25);
        expect(getMinSpan(0, fd25)).toBe(fd25);
        // A sound: 0.01 s about 8 px wide
        expect(getMinSpan(800, 0.01, 8)).toBeCloseTo(1);
    });

    it('stays within the media', () => {
        expect(clampView({start: -5, span: 10}, 60, 1)).toEqual({
            start: 0,
            span: 10,
        });
        expect(clampView({start: 55, span: 10}, 60, 1)).toEqual({
            start: 50,
            span: 10,
        });
        expect(clampView({start: 0, span: 100}, 60, 1)).toEqual(fullView(60));
        expect(clampView({start: 30, span: 0.1}, 60, 1)).toEqual({
            start: 30,
            span: 1,
        });
        expect(clampView({start: 3, span: 4}, 0, 1)).toEqual({
            start: 0,
            span: 0,
        });
    });

    it('zooms around the anchor', () => {
        const view = zoomView({start: 0, span: 100}, 2, 25, 100, 1);
        expect(view).toEqual({start: 12.5, span: 50});
        // The anchor stays at the same place
        expect(timeToX(25, view, 1000)).toBeCloseTo(
            timeToX(25, {start: 0, span: 100}, 1000)
        );

        const out = zoomView(view, 0.5, 25, 100, 1);
        expect(out).toEqual({start: 0, span: 100});
    });

    it('bounds the zoom', () => {
        expect(zoomView({start: 40, span: 2}, 100, 41, 100, 1)).toEqual({
            start: 40.5,
            span: 1,
        });
        expect(zoomView({start: 0, span: 50}, 0.01, 10, 100, 1)).toEqual({
            start: 0,
            span: 100,
        });
        // Anchored at the end: stays within the media
        const end = zoomView({start: 50, span: 50}, 0.5, 100, 100, 1);
        expect(end).toEqual({start: 0, span: 100});
    });

    it('pans within the media', () => {
        expect(panView({start: 10, span: 20}, 5, 60)).toEqual({
            start: 15,
            span: 20,
        });
        expect(panView({start: 10, span: 20}, -50, 60)).toEqual({
            start: 0,
            span: 20,
        });
        expect(panView({start: 10, span: 20}, 50, 60)).toEqual({
            start: 40,
            span: 20,
        });
        expect(centerView({start: 0, span: 20}, 30, 60)).toEqual({
            start: 20,
            span: 20,
        });
    });

    it('follows the playhead page by page', () => {
        const view = {start: 10, span: 20};
        expect(followPlayhead(view, 15, 100)).toBe(view);
        expect(followPlayhead(view, 30, 100)).toBe(view);
        expect(followPlayhead(view, 31, 100)).toEqual({start: 31, span: 20});
        expect(followPlayhead(view, 5, 100)).toEqual({start: 0, span: 20});
        expect(followPlayhead(view, 95, 100)).toEqual({start: 80, span: 20});
        expect(followPlayhead({start: 50, span: 20}, 40, 100)).toEqual({
            start: 20,
            span: 20,
        });
    });
});

describe('frames', () => {
    it('snaps to the nearest frame', () => {
        expect(snapToFrame(1.01, fd25)).toBe(1);
        expect(snapToFrame(1.03, fd25)).toBe(1.04);
        expect(snapToFrame(0.1, fd25)).toBe(0.12);
        // 29.97 fps: no float noise
        const ntsc = 1001 / 30000;
        expect(snapToFrame(1, ntsc)).toBe(Math.round(30 * ntsc * 1e6) / 1e6);
        expect(snapToFrame(1.234, 0)).toBe(1.234);
    });

    it('numbers the frames from 0', () => {
        expect(frameIndex(0, fd25)).toBe(0);
        expect(frameIndex(0.039, fd25)).toBe(0);
        expect(frameIndex(0.04, fd25)).toBe(1);
        // A snapped time is the start of its frame, despite the float noise
        expect(frameIndex(snapToFrame(0.12, fd25), fd25)).toBe(3);
        expect(frameIndex(10, fd25)).toBe(250);
        expect(frameIndex(-1, fd25)).toBe(0);
    });
});

describe('ticks', () => {
    it('steps from frames to hours', () => {
        const scale = getTickScale(fd25, true);
        expect(scale.slice(0, 3)).toEqual([
            {unit: 'frames', count: 1, seconds: fd25},
            {unit: 'frames', count: 2, seconds: 2 * fd25},
            {unit: 'seconds', seconds: 0.1},
        ]);
        expect(scale[scale.length - 1]).toEqual({
            unit: 'seconds',
            seconds: 3600,
        });
        // 60 fps: up to 5 frames below a tenth of a second
        expect(
            getTickScale(1 / 60, true)
                .filter(s => s.unit === 'frames')
                .map(s => s.unit === 'frames' && s.count)
        ).toEqual([1, 2, 5]);
        // A sound: hundredths of a second, no frame
        expect(getTickScale(0.01, false)[0]).toEqual({
            unit: 'seconds',
            seconds: 0.01,
        });
    });

    it('graduates every frame at the deepest zoom', () => {
        const width = 1000;
        const view = {start: 2, span: getMinSpan(width, fd25)};
        const {minor, major, ticks} = getTicks(view, width, fd25);
        expect(minor).toEqual({unit: 'frames', count: 1, seconds: fd25});
        // 0.2 s = 5 frames = 120 px, the first labelled multiple ≥ 80 px
        expect(major).toEqual({unit: 'seconds', seconds: 0.2});
        expect(ticks[0]).toEqual({time: 2, major: true});
        expect(ticks[1]).toEqual({time: 2.04, major: false});
        expect(ticks.filter(t => t.major).map(t => t.time)).toEqual([
            2, 2.2, 2.4, 2.6, 2.8, 3, 3.2, 3.4, 3.6,
        ]);
    });

    it('graduates seconds and minutes when zoomed out', () => {
        // 10 min over 1000 px: 1.67 px per second
        const {minor, major, ticks} = getTicks(fullView(600), 1000, fd25);
        expect(minor).toEqual({unit: 'seconds', seconds: 5});
        expect(major).toEqual({unit: 'seconds', seconds: 60});
        expect(ticks).toHaveLength(121);
        expect(ticks.filter(t => t.major)).toHaveLength(11);

        // 1 min over 1000 px: 16.7 px per second
        const minute = getTicks(fullView(60), 1000, fd25);
        expect(minute.minor.seconds).toBe(0.5);
        expect(minute.major.seconds).toBe(5);
    });

    it('keeps the ticks at least 8 px apart', () => {
        for (const span of [0.5, 3, 47, 600, 7200, 36000]) {
            const {minor, major} = getTicks({start: 0, span}, 800, fd25);
            expect((minor.seconds / span) * 800).toBeGreaterThanOrEqual(8);
            expect((major.seconds / span) * 800).toBeGreaterThanOrEqual(80);
        }
    });

    it('labels the ticks', () => {
        const second = {unit: 'seconds', seconds: 1} as const;
        expect(formatTickLabel(0, second)).toBe('0:00');
        expect(formatTickLabel(75, second)).toBe('1:15');
        expect(formatTickLabel(3725, second)).toBe('1:02:05');
        expect(formatTickLabel(2.2, {unit: 'seconds', seconds: 0.2})).toBe(
            '0:02.2'
        );
        expect(
            formatTickLabel(2.04, {unit: 'frames', count: 1, seconds: fd25})
        ).toBe('0:02.04');
    });

    it('formats a step', () => {
        expect(formatStepDuration(0.5)).toBe('0.5 s');
        expect(formatStepDuration(10)).toBe('10 s');
        expect(formatStepDuration(300)).toBe('5 min');
        expect(formatStepDuration(3600)).toBe('1 h');
    });
});
