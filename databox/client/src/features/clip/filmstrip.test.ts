import {afterEach, describe, expect, it, vi} from 'vitest';
import {
    extractVideoFrames,
    FrameCache,
    FRAME_TIMEOUT,
    getFilmstripCount,
    getFilmstripSlots,
    getFilmstripTimes,
    MAX_FILMSTRIP_FRAMES,
} from './filmstrip';

/**
 * Video element double: jsdom implements neither the loading nor the seek.
 * A seek answers `seeked` in a microtask, unless the time is in `stuck`
 * (never answers) or `broken` (answers `error`).
 */
class FakeVideo extends EventTarget {
    readyState = 4;
    seeks: number[] = [];
    stuck = new Set<number>();
    broken = new Set<number>();
    private time = 0;

    get currentTime() {
        return this.time;
    }

    set currentTime(time: number) {
        this.time = time;
        this.seeks.push(time);
        if (this.stuck.has(time)) {
            return;
        }
        const event = this.broken.has(time) ? 'error' : 'seeked';
        queueMicrotask(() => this.dispatchEvent(new Event(event)));
    }
}

const flush = async () => {
    for (let i = 0; i < 10; i++) {
        await Promise.resolve();
    }
};

describe('getFilmstripCount', () => {
    it('fills the width, the last thumbnail being cut', () => {
        // 48 × 16/9 = 85.33 px per thumbnail
        expect(getFilmstripCount(512, 48, 16 / 9)).toBe(6);
        expect(getFilmstripCount(513, 48, 16 / 9)).toBe(7);
        // Portrait video: narrower thumbnails
        expect(getFilmstripCount(270, 48, 9 / 16)).toBe(10);
    });

    it('defaults to 16/9', () => {
        expect(getFilmstripCount(512, 48)).toBe(6);
        expect(getFilmstripCount(512, 48, 0)).toBe(6);
    });

    it('is bounded', () => {
        expect(getFilmstripCount(0, 48, 16 / 9)).toBe(1);
        expect(getFilmstripCount(10, 48, 16 / 9)).toBe(1);
        expect(getFilmstripCount(100_000, 48, 16 / 9)).toBe(
            MAX_FILMSTRIP_FRAMES
        );
        expect(getFilmstripCount(500, 0, 16 / 9)).toBe(1);
    });

    it('shows each frame once at most', () => {
        expect(getFilmstripCount(1000, 48, 16 / 9, 3)).toBe(3);
        expect(getFilmstripCount(1000, 48, 16 / 9, 3.7)).toBe(3);
        expect(getFilmstripCount(1000, 48, 16 / 9, 100)).toBe(12);
        expect(getFilmstripCount(1000, 48, 16 / 9, 0)).toBe(12);
    });
});

describe('getFilmstripTimes', () => {
    it('takes the middle of each slice', () => {
        expect(getFilmstripTimes(0, 10, 4, 10)).toEqual([
            1.25, 3.75, 6.25, 8.75,
        ]);
    });

    it('takes the slices of the window', () => {
        expect(getFilmstripTimes(10, 20, 2, 60)).toEqual([12.5, 17.5]);
        // Kept before the end of the media
        expect(getFilmstripTimes(58, 62, 2, 60)).toEqual([59, 59.95]);
    });

    it('stays increasing and before the end', () => {
        const times = getFilmstripTimes(0, 0.1, 3, 0.1);
        expect(times).toHaveLength(3);
        times.forEach((t, i) => {
            expect(t).toBeLessThan(0.1);
            if (i > 0) {
                expect(t).toBeGreaterThanOrEqual(times[i - 1]);
            }
        });

        const many = getFilmstripTimes(0, 125.4, 60, 125.4);
        many.forEach((t, i) => {
            expect(t).toBeLessThan(125.4);
            if (i > 0) {
                expect(t).toBeGreaterThan(many[i - 1]);
            }
        });
    });

    it('has nothing to take without a duration', () => {
        expect(getFilmstripTimes(0, 0, 5, 0)).toEqual([]);
        expect(getFilmstripTimes(0, 10, 5, Number.NaN)).toEqual([]);
        expect(getFilmstripTimes(0, 10, 0, 10)).toEqual([]);
        expect(getFilmstripTimes(5, 5, 2, 10)).toEqual([]);
    });
});

describe('getFilmstripSlots', () => {
    it('covers the whole media', () => {
        expect(getFilmstripSlots({start: 0, span: 10}, 4, 10)).toEqual([
            {start: 0, time: 1.25},
            {start: 2.5, time: 3.75},
            {start: 5, time: 6.25},
            {start: 7.5, time: 8.75},
        ]);
    });

    it('keeps the same instants while scrolling', () => {
        const slots = getFilmstripSlots({start: 1, span: 10}, 4, 20);
        expect(slots.map(s => s.start)).toEqual([0, 2.5, 5, 7.5, 10]);
        expect(slots.map(s => s.time)).toEqual([1.25, 3.75, 6.25, 8.75, 11.25]);
        expect(getFilmstripSlots({start: 1.5, span: 10}, 4, 20)).toEqual(slots);
        expect(
            getFilmstripSlots({start: 10, span: 10}, 4, 20).map(s => s.time)
        ).toEqual([11.25, 13.75, 16.25, 18.75]);
    });

    it('has nothing to show without a duration', () => {
        expect(getFilmstripSlots({start: 0, span: 10}, 4, 0)).toEqual([]);
        expect(getFilmstripSlots({start: 0, span: 0}, 4, 10)).toEqual([]);
        expect(getFilmstripSlots({start: 0, span: 10}, 0, 10)).toEqual([]);
    });
});

describe('FrameCache', () => {
    it('forgets the least recently used frame', () => {
        const cache = new FrameCache<string>(2);
        cache.set(1, 'a');
        cache.set(2, 'b');
        expect(cache.get(1)).toBe('a');
        cache.set(3, 'c');
        expect(cache.has(2)).toBe(false);
        expect(cache.get(1)).toBe('a');
        expect(cache.get(3)).toBe('c');
        expect(cache.size).toBe(2);

        cache.set(3, 'd');
        expect(cache.get(3)).toBe('d');
        expect(cache.size).toBe(2);
    });
});

describe('extractVideoFrames', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('draws every frame in order, one seek at a time', async () => {
        const video = new FakeVideo();
        const frames: [number, number][] = [];
        await extractVideoFrames(
            video,
            [1, 2, 3],
            (i, v) => {
                // The seek is done: only one at a time
                expect(video.seeks).toHaveLength(i + 1);
                frames.push([i, v.currentTime]);
            },
            new AbortController().signal
        );

        expect(frames).toEqual([
            [0, 1],
            [1, 2],
            [2, 3],
        ]);
    });

    it('waits for the video data', async () => {
        const video = new FakeVideo();
        video.readyState = 0;
        const onFrame = vi.fn();
        const done = extractVideoFrames(
            video,
            [1, 2],
            onFrame,
            new AbortController().signal
        );
        await flush();
        expect(video.seeks).toEqual([]);

        video.readyState = 2;
        video.dispatchEvent(new Event('loadeddata'));
        await done;
        expect(video.seeks).toEqual([1, 2]);
        expect(onFrame).toHaveBeenCalledTimes(2);
    });

    it('gives up when the video does not load', async () => {
        const video = new FakeVideo();
        video.readyState = 0;
        const onFrame = vi.fn();
        const done = extractVideoFrames(
            video,
            [1, 2],
            onFrame,
            new AbortController().signal
        );
        video.dispatchEvent(new Event('error'));
        await done;
        expect(video.seeks).toEqual([]);
        expect(onFrame).not.toHaveBeenCalled();
    });

    it('stops on abort', async () => {
        const video = new FakeVideo();
        const controller = new AbortController();
        const onFrame = vi.fn((i: number) => {
            if (i === 1) {
                controller.abort();
            }
        });
        await extractVideoFrames(
            video,
            [1, 2, 3, 4],
            onFrame,
            controller.signal
        );

        expect(onFrame).toHaveBeenCalledTimes(2);
        expect(video.seeks).toEqual([1, 2]);
    });

    it('stops on abort while seeking', async () => {
        const video = new FakeVideo();
        video.stuck.add(2);
        const controller = new AbortController();
        const onFrame = vi.fn();
        const done = extractVideoFrames(
            video,
            [1, 2, 3],
            onFrame,
            controller.signal
        );
        await flush();
        expect(video.seeks).toEqual([1, 2]);

        controller.abort();
        await done;
        // The late answer of the seek draws nothing
        video.dispatchEvent(new Event('seeked'));
        expect(onFrame).toHaveBeenCalledTimes(1);
        expect(video.seeks).toEqual([1, 2]);
    });

    it('does nothing once aborted', async () => {
        const video = new FakeVideo();
        video.readyState = 0;
        const controller = new AbortController();
        controller.abort();
        const onFrame = vi.fn();
        await extractVideoFrames(video, [1, 2], onFrame, controller.signal);

        expect(video.seeks).toEqual([]);
        expect(onFrame).not.toHaveBeenCalled();
    });

    it('skips a frame which takes too long', async () => {
        vi.useFakeTimers();
        const video = new FakeVideo();
        video.stuck.add(2);
        const onFrame = vi.fn();
        const done = extractVideoFrames(
            video,
            [1, 2, 3],
            onFrame,
            new AbortController().signal
        );
        await vi.advanceTimersByTimeAsync(FRAME_TIMEOUT - 1);
        expect(video.seeks).toEqual([1, 2]);

        await vi.advanceTimersByTimeAsync(1);
        await done;
        expect(video.seeks).toEqual([1, 2, 3]);
        expect(onFrame.mock.calls.map(c => c[0])).toEqual([0, 2]);
    });

    it('skips a frame which fails', async () => {
        const video = new FakeVideo();
        video.broken.add(2);
        const onFrame = vi.fn();
        await extractVideoFrames(
            video,
            [1, 2, 3],
            onFrame,
            new AbortController().signal
        );

        expect(video.seeks).toEqual([1, 2, 3]);
        expect(onFrame.mock.calls.map(c => c[0])).toEqual([0, 2]);
    });
});
