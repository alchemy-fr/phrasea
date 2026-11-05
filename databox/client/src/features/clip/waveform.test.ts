import {describe, expect, it} from 'vitest';
import {waveformPath} from './waveform';

/** The points of a path made of `M`/`L` commands */
function points(path: string): [number, number][] {
    return [...path.matchAll(/[ML](-?[\d.]+) (-?[\d.]+)/g)].map(m => [
        Number(m[1]),
        Number(m[2]),
    ]);
}

describe('waveformPath', () => {
    it('scales the loudest peak to the edges', () => {
        const ys = points(waveformPath([0.1, -0.4, 0.2])).map(([, y]) => y);
        expect(Math.min(...ys)).toBe(0);
        expect(Math.max(...ys)).toBe(2);
        // 0.1 / 0.4 of the half height
        expect(ys).toContain(0.75);
        expect(ys).toContain(1.25);
    });

    it('is symmetric around the middle line', () => {
        const path = waveformPath([0.3, 0.9, -0.6, 0.1]);
        const pts = points(path);
        // Start, 4 peaks above, end, 4 peaks below
        expect(pts).toHaveLength(10);
        expect(pts[0]).toEqual([0, 1]);
        expect(pts[5]).toEqual([4, 1]);
        const top = pts.slice(1, 5);
        const bottom = pts.slice(6).reverse();
        top.forEach(([x, y], i) => {
            expect(bottom[i][0]).toBe(x);
            expect(bottom[i][1] - 1).toBeCloseTo(1 - y);
        });
        expect(path.endsWith('Z')).toBe(true);
    });

    it('spreads the peaks over the width', () => {
        const xs = points(waveformPath([1, 1, 1])).map(([x]) => x);
        expect(Math.min(...xs)).toBe(0);
        expect(Math.max(...xs)).toBe(3);
    });

    it('is flat without any sound', () => {
        for (const peaks of [[], [0, 0, 0]]) {
            const path = waveformPath(peaks);
            expect(path).not.toContain('NaN');
            const pts = points(path);
            expect(pts.length).toBeGreaterThan(1);
            expect(pts.every(([, y]) => y === 1)).toBe(true);
        }
    });
});
