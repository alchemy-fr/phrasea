import {describe, expect, it} from 'vitest';
import {
    clampOffset,
    fitScale,
    initialView,
    MAX_ZOOM,
    nextZoomStep,
    toScale,
    toZoom,
    wheelFactor,
    zoomView,
    type ZoomMetrics,
} from './zoom';

// A 4000×2000 image fitted in a 1000×800 viewport: laid out at 1000×500
const large: ZoomMetrics = {cw: 1000, ch: 800, fw: 1000, fh: 500, nw: 4000};
// A 200×100 image, smaller than the viewport: laid out at its natural size
const small: ZoomMetrics = {cw: 1000, ch: 800, fw: 200, fh: 100, nw: 200};

describe('fitScale', () => {
    it('is 1 without rotation, the layout already fits', () => {
        expect(fitScale(large, 0)).toBe(1);
        expect(fitScale(small, 0)).toBe(1);
    });

    it('fits the rotated image in the viewport', () => {
        // Rotated: 500×1000, the height is bound to 800
        expect(fitScale(large, 90)).toBeCloseTo(0.8);
        expect(fitScale(large, -90)).toBeCloseTo(0.8);
        expect(fitScale(large, 180)).toBe(1);
    });

    it('never enlarges beyond the natural size', () => {
        expect(fitScale(small, 90)).toBe(1);
    });
});

describe('toZoom / toScale', () => {
    it('relates the scale to the natural size', () => {
        expect(toZoom(large, 1)).toBe(0.25);
        expect(toScale(large, 1)).toBe(4);
        expect(toZoom(small, 1)).toBe(1);
    });
});

describe('zoomView', () => {
    it('keeps the point under the pointer in place', () => {
        const v = zoomView(large, initialView, 2, {x: 100, y: 50});
        expect(v.scale).toBe(2);
        // The image point under the pointer was 100px right of its center,
        // it is now 200px right of it
        expect(v.x).toBe(-100);
        expect(v.y).toBe(-50);
    });

    it('does not go below the fitted scale nor beyond the max zoom', () => {
        expect(zoomView(large, initialView, 0.1).scale).toBe(1);
        expect(zoomView(large, initialView, 1000).scale).toBe(
            toScale(large, MAX_ZOOM)
        );
    });
});

describe('clampOffset', () => {
    it('centers a side smaller than the viewport', () => {
        expect(clampOffset(large, {...initialView, x: 50, y: 50})).toEqual(
            initialView
        );
    });

    it('pans a larger side until its edge', () => {
        // 2000×1000 in 1000×800: 500px of margin horizontally, 100 vertically
        const v = clampOffset(large, {
            ...initialView,
            scale: 2,
            x: 900,
            y: -900,
        });
        expect(v.x).toBe(500);
        expect(v.y).toBe(-100);
    });
});

describe('nextZoomStep', () => {
    it('steps through the levels, fit included', () => {
        expect(nextZoomStep(0.25, 0.25, 1)).toBe(0.5);
        expect(nextZoomStep(0.3, 0.3, 1)).toBe(0.5);
        expect(nextZoomStep(0.6, 0.3, -1)).toBe(0.5);
        expect(nextZoomStep(0.45, 0.3, -1)).toBe(0.3);
    });

    it('stops at both ends', () => {
        expect(nextZoomStep(0.3, 0.3, -1)).toBeUndefined();
        expect(nextZoomStep(MAX_ZOOM, 0.3, 1)).toBeUndefined();
    });

    it('tolerates a level close to a step', () => {
        expect(nextZoomStep(0.999, 0.25, 1)).toBe(1.5);
        expect(nextZoomStep(1.001, 0.25, -1)).toBe(0.75);
    });
});

describe('wheelFactor', () => {
    it('zooms in when scrolling up, out when scrolling down', () => {
        expect(
            wheelFactor({deltaY: -100, deltaMode: 0, ctrlKey: false})
        ).toBeGreaterThan(1);
        expect(
            wheelFactor({deltaY: 100, deltaMode: 0, ctrlKey: false})
        ).toBeLessThan(1);
    });

    it('is proportional to the delta, bounded per event', () => {
        const small = wheelFactor({deltaY: -4, deltaMode: 0, ctrlKey: false});
        const notch = wheelFactor({deltaY: -100, deltaMode: 0, ctrlKey: false});
        const huge = wheelFactor({deltaY: -1000, deltaMode: 0, ctrlKey: false});
        expect(small).toBeLessThan(notch);
        expect(huge).toBe(notch);
    });

    it('reads line deltas as pixels', () => {
        expect(wheelFactor({deltaY: -3, deltaMode: 1, ctrlKey: false})).toBe(
            wheelFactor({deltaY: -48, deltaMode: 0, ctrlKey: false})
        );
    });
});
