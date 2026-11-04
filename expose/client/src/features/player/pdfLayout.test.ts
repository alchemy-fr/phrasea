import {describe, expect, it} from 'vitest';
import {
    canvasPixelRatio,
    currentPage,
    fitScale,
    layoutPages,
    PAGE_GAP,
    PAGE_PADDING,
    pageAt,
    pagesToRender,
} from './pdfLayout';

const a4 = {width: 600, height: 800};

describe('layoutPages', () => {
    it('stacks the pages, the unknown ones the size of the first', () => {
        const layout = layoutPages(3, {2: {width: 800, height: 600}}, a4, 0.5);

        expect(layout.tops).toEqual([
            PAGE_PADDING,
            PAGE_PADDING + 400 + PAGE_GAP,
            PAGE_PADDING + 400 + PAGE_GAP + 300 + PAGE_GAP,
        ]);
        expect(layout.widths).toEqual([300, 400, 300]);
        expect(layout.width).toBe(400);
        expect(layout.height).toBe(
            PAGE_PADDING + 400 + 300 + 400 + 2 * PAGE_GAP + PAGE_PADDING
        );
    });

    it('finds the page at a position and the one most in sight', () => {
        const layout = layoutPages(3, {}, a4, 1);
        // Pages at 16, 828, 1640
        expect(pageAt(layout, 0)).toBe(1);
        expect(pageAt(layout, 820)).toBe(1);
        expect(pageAt(layout, 830)).toBe(2);
        expect(pageAt(layout, 10000)).toBe(3);

        expect(currentPage(layout, 0, 600)).toBe(1);
        expect(currentPage(layout, 500, 600)).toBe(1);
        expect(currentPage(layout, 600, 600)).toBe(2);
        expect(currentPage(layout, 1600, 600)).toBe(3);
    });

    it('renders the pages in sight and a viewport around', () => {
        const layout = layoutPages(10, {}, a4, 1);

        expect(pagesToRender(layout, 0, 600)).toEqual([1, 2]);
        expect(pagesToRender(layout, 5000, 600)).toEqual([6, 7, 8]);
    });
});

describe('fitScale', () => {
    it('fits the width of the page, or the whole page', () => {
        const viewport = {width: 632, height: 432};

        expect(fitScale(a4, viewport, 'width')).toBe(1);
        expect(fitScale(a4, viewport, 'page')).toBe(0.5);
    });
});

describe('canvasPixelRatio', () => {
    it('draws at the density of the screen, less for a huge page', () => {
        expect(canvasPixelRatio(1000, 1400, 2)).toBe(2);
        expect(canvasPixelRatio(8000, 11000, 2)).toBeLessThan(1);
    });
});
