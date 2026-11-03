import {clamp} from '@/lib/utils/misc';

/** Size of a page at scale 1 (PDF points, rotation applied) */
export type PageSize = {width: number; height: number};

/** Space around the pages and between two of them, in screen pixels */
export const PAGE_PADDING = 16;
export const PAGE_GAP = 12;

export const MIN_PDF_ZOOM = 0.1;
export const MAX_PDF_ZOOM = 8;

/**
 * Pixels a page canvas may have: beyond, it is drawn at a lower resolution
 * than the screen's (a page zoomed a lot, on a high density screen)
 */
const MAX_CANVAS_PIXELS = 2 ** 25;

/**
 * The pages stacked vertically at a scale, in pixels of the scrolled
 * content: the pages whose size is not known yet take the one of the first
 * page.
 */
export type PagesLayout = {
    tops: number[];
    widths: number[];
    heights: number[];
    /** Widest page */
    width: number;
    /** Height of the content, padding included */
    height: number;
};

export function layoutPages(
    numPages: number,
    sizes: Record<number, PageSize>,
    fallback: PageSize,
    scale: number
): PagesLayout {
    const tops: number[] = [];
    const widths: number[] = [];
    const heights: number[] = [];
    let y = PAGE_PADDING;
    let width = 0;
    for (let n = 1; n <= numPages; n++) {
        const size = sizes[n] ?? fallback;
        const w = size.width * scale;
        const h = size.height * scale;
        tops.push(y);
        widths.push(w);
        heights.push(h);
        width = Math.max(width, w);
        y += h + PAGE_GAP;
    }

    return {
        tops,
        widths,
        heights,
        width,
        height: (numPages > 0 ? y - PAGE_GAP : y) + PAGE_PADDING,
    };
}

/** The page (1-based) at a vertical position of the content */
export function pageAt(layout: PagesLayout, y: number): number {
    const {tops, heights} = layout;
    for (let i = 0; i < tops.length; i++) {
        // The gap below a page belongs to it
        if (y < tops[i] + heights[i] + PAGE_GAP) {
            return i + 1;
        }
    }

    return Math.max(1, tops.length);
}

/** The page taking the most of the viewport, the first one on a tie */
export function currentPage(
    layout: PagesLayout,
    scrollTop: number,
    viewportHeight: number
): number {
    const bottom = scrollTop + viewportHeight;
    let best = 1;
    let bestVisible = 0;
    for (let i = 0; i < layout.tops.length; i++) {
        const top = layout.tops[i];
        if (top >= bottom) {
            break;
        }
        const visible =
            Math.min(bottom, top + layout.heights[i]) -
            Math.max(scrollTop, top);
        if (visible > bestVisible) {
            best = i + 1;
            bestVisible = visible;
        }
    }

    return best;
}

/**
 * The pages to render: the ones in the viewport and in `overscan`
 * viewports above and below it.
 */
export function pagesToRender(
    layout: PagesLayout,
    scrollTop: number,
    viewportHeight: number,
    overscan = 1
): number[] {
    const margin = Math.max(viewportHeight, 1) * overscan;
    const top = scrollTop - margin;
    const bottom = scrollTop + Math.max(viewportHeight, 1) + margin;
    const pages: number[] = [];
    for (let i = 0; i < layout.tops.length; i++) {
        const pageTop = layout.tops[i];
        if (pageTop > bottom) {
            break;
        }
        if (pageTop + layout.heights[i] >= top) {
            pages.push(i + 1);
        }
    }

    return pages;
}

export type PdfFit = 'width' | 'page';

/** The scale fitting the page to the width of the viewport, or whole */
export function fitScale(
    size: PageSize,
    viewport: {width: number; height: number},
    fit: PdfFit
): number {
    const width = (viewport.width - 2 * PAGE_PADDING) / size.width;
    const scale =
        fit === 'width'
            ? width
            : Math.min(
                  width,
                  (viewport.height - 2 * PAGE_PADDING) / size.height
              );

    return clamp(scale, MIN_PDF_ZOOM, MAX_PDF_ZOOM);
}

/**
 * The device pixel ratio a page is drawn with: the screen's, lowered for a
 * page too large to be drawn sharp
 */
export function canvasPixelRatio(
    width: number,
    height: number,
    screenRatio: number
): number {
    const area = width * height;
    if (area <= 0) {
        return screenRatio;
    }

    return Math.max(
        0.5,
        Math.min(screenRatio, Math.sqrt(MAX_CANVAS_PIXELS / area))
    );
}
