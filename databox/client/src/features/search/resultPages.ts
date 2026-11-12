import type {Asset} from '@/types/api';

/**
 * Adds the next page of results. Pages are numbered: an asset added (or
 * re-sorted) ahead while scrolling shifts the next page, which then repeats
 * assets already listed. They are dropped, so that the grid and the viewer
 * navigation list each asset once.
 */
export function appendPage(pages: Asset[][], items: Asset[]): Asset[][] {
    const known = new Set<string>();
    pages.forEach(page => page.forEach(a => known.add(a.id)));

    return [...pages, items.filter(a => !known.has(a.id))];
}
