import {describe, expect, it} from 'vitest';
import {isPointInRectangle, normalizeRectangleProps} from './rectangle';
import {getDrawingBoundingBox} from './draw';
import {isPointInCircle} from './circle';

describe('normalizeRectangleProps', () => {
    it('keeps a rectangle drawn rightwards and downwards', () => {
        expect(normalizeRectangleProps({x: 1, y: 2, w: 3, h: 4})).toEqual({
            x: 1,
            y: 2,
            w: 3,
            h: 4,
        });
    });

    it('puts a rectangle drawn backwards straight', () => {
        expect(normalizeRectangleProps({x: 5, y: 6, w: -3, h: -4})).toEqual({
            x: 2,
            y: 2,
            w: 3,
            h: 4,
        });
    });
});

describe('isPointInRectangle', () => {
    it('includes the edges', () => {
        const rect = {x: 10, y: 10, w: 20, h: 10};
        expect(isPointInRectangle(10, 10, rect)).toBe(true);
        expect(isPointInRectangle(30, 20, rect)).toBe(true);
        expect(isPointInRectangle(31, 20, rect)).toBe(false);
        expect(isPointInRectangle(15, 9, rect)).toBe(false);
    });
});

describe('isPointInCircle', () => {
    it('is strictly inside the radius', () => {
        const circle = {x: 0, y: 0, radius: 5};
        expect(isPointInCircle(3, 3, circle)).toBe(true);
        expect(isPointInCircle(5, 0, circle)).toBe(false);
    });
});

describe('getDrawingBoundingBox', () => {
    it('bounds every path, moved by the offset', () => {
        expect(
            getDrawingBoundingBox({
                x: 0.1,
                y: 0.2,
                paths: [
                    [
                        {x: 0.1, y: 0.5},
                        {x: 0.4, y: 0.3},
                    ],
                    [{x: 0.2, y: 0.9}],
                ],
            })
        ).toEqual({
            x: 0.1 + 0.1,
            y: 0.2 + 0.3,
            w: 0.4 - 0.1,
            h: 0.9 - 0.3,
        });
    });
});
