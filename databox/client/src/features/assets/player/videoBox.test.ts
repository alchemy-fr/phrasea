import {describe, expect, it} from 'vitest';
import {containBox} from './videoBox';

describe('containBox', () => {
    const element = {left: 10, top: 20, width: 1000, height: 500};

    it('letterboxes a video narrower than its element', () => {
        // 4:3 in 2:1: full height, centered horizontally
        expect(containBox(element, 640, 480)).toEqual({
            left: 10 + (1000 - 500 * (4 / 3)) / 2,
            top: 20,
            width: 500 * (4 / 3),
            height: 500,
        });
    });

    it('letterboxes a video wider than its element', () => {
        // 4:1 in 2:1: full width, centered vertically
        expect(containBox(element, 400, 100)).toEqual({
            left: 10,
            top: 20 + 125,
            width: 1000,
            height: 250,
        });
    });

    it('is the element when it has the ratio of the video, or the size is unknown', () => {
        expect(containBox(element, 1920, 960)).toEqual(element);
        expect(containBox(element, 0, 0)).toEqual(element);
    });
});
