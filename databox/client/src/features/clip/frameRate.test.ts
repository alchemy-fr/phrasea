import {describe, expect, it} from 'vitest';
import {estimateFrameRate, measureFrameRate} from './frameRate';

/** Media times of `count` frames at `fps`, from `start` */
const frames = (fps: number, count: number, start = 0) =>
    Array.from({length: count}, (_, i) => start + i / fps);

describe('estimateFrameRate', () => {
    it('finds the usual frame rates', () => {
        expect(estimateFrameRate(frames(25, 20))).toBe(25);
        expect(estimateFrameRate(frames(30000 / 1001, 20, 3.2))).toBe(29.97);
        expect(estimateFrameRate(frames(24000 / 1001, 20))).toBe(23.976);
        expect(estimateFrameRate(frames(24, 20))).toBe(24);
        expect(estimateFrameRate(frames(60, 20))).toBe(60);
        expect(estimateFrameRate(frames(50, 20))).toBe(50);
    });

    it('resists the noise and the skipped frames', () => {
        const noisy = frames(25, 20).map(
            (t, i) => t + (i % 2 ? 0.002 : -0.002)
        );
        expect(estimateFrameRate(noisy)).toBe(25);

        // Two frames skipped by the callback
        const skipped = frames(25, 20).filter((_, i) => i !== 5 && i !== 12);
        expect(estimateFrameRate(skipped)).toBe(25);
    });

    it('rounds an unusual frame rate to the hundredth', () => {
        expect(estimateFrameRate(frames(15, 20))).toBe(15);
        expect(estimateFrameRate(frames(12.5, 20))).toBe(12.5);
        expect(estimateFrameRate(frames(40, 20))).toBe(40);
    });

    it('ignores the repeated and backward times', () => {
        const times = frames(25, 12);
        const repeated = [
            ...times.slice(0, 6),
            times[5],
            times[5],
            ...times.slice(6),
        ];
        expect(estimateFrameRate(repeated)).toBe(25);
        expect(estimateFrameRate([...times, 0, 0.04])).toBe(25);
    });

    it('needs enough frames', () => {
        expect(estimateFrameRate([])).toBeUndefined();
        expect(estimateFrameRate([1])).toBeUndefined();
        expect(estimateFrameRate(frames(25, 5))).toBeUndefined();
        expect(estimateFrameRate([0, 0, 0, 0, 0, 0, 0, 0])).toBeUndefined();
        expect(estimateFrameRate(frames(25, 6))).toBe(25);
    });
});

describe('measureFrameRate', () => {
    it('gives up without requestVideoFrameCallback', async () => {
        await expect(
            measureFrameRate('video.mp4', new AbortController().signal)
        ).resolves.toBeUndefined();
    });
});
