'use client';

import {useCallback} from 'react';

const COLUMN_SELECTOR = ':scope > [data-synced-column]';

function scrollParent(element: HTMLElement): HTMLElement | undefined {
    for (let p = element.parentElement; p; p = p.parentElement) {
        if (/(auto|scroll)/.test(getComputedStyle(p).overflowY)) {
            return p;
        }
    }

    return undefined;
}

/**
 * Scrolls the sticky columns of the grid with the page, a single scroll
 * showing all of them: each one goes from its top to its bottom while the
 * grid passes under it. The grid is as tall as its longest column, so that
 * the page has that much to scroll.
 *
 * Returns the ref of the grid. A column is a child of the grid marked
 * `data-synced-column`, sticky, of a limited height and with a hidden
 * overflow, holding its content in a single element. A column that is not
 * sticky (narrow screens) is left alone.
 */
export function useSyncedColumns(): (
    element: HTMLElement | null
) => (() => void) | undefined {
    return useCallback((element: HTMLElement | null) => {
        if (!element) {
            return undefined;
        }
        const columns = [
            ...element.querySelectorAll<HTMLElement>(COLUMN_SELECTOR),
        ];

        let frame = 0;
        const sync = () => {
            frame = 0;
            const sticky = columns.filter(
                c => getComputedStyle(c).position === 'sticky'
            );
            element.style.minHeight = sticky.length
                ? `${Math.max(...sticky.map(c => c.scrollHeight))}px`
                : '';
            const gridTop = element.getBoundingClientRect().top;
            const gridHeight = element.clientHeight;
            const viewportTop =
                scrollParent(element)?.getBoundingClientRect().top ?? 0;
            for (const column of sticky) {
                const stickyTop =
                    viewportTop + parseFloat(getComputedStyle(column).top);
                // How far the grid went above the column since it stuck
                const passed = stickyTop - gridTop;
                const travel = gridHeight - column.clientHeight;
                const progress =
                    travel > 0 ? Math.min(Math.max(passed / travel, 0), 1) : 0;
                column.scrollTop =
                    progress * (column.scrollHeight - column.clientHeight);
            }
        };
        const schedule = () => {
            if (!frame) {
                frame = requestAnimationFrame(sync);
            }
        };

        // Scroll events do not bubble: caught on their way down from wherever
        // the page scrolls, but for the columns scrolled here
        const onScroll = (e: Event) => {
            if (!columns.includes(e.target as HTMLElement)) {
                schedule();
            }
        };
        document.addEventListener('scroll', onScroll, {
            capture: true,
            passive: true,
        });
        window.addEventListener('resize', schedule);
        const observer = new ResizeObserver(schedule);
        observer.observe(element);
        for (const column of columns) {
            if (column.firstElementChild) {
                observer.observe(column.firstElementChild);
            }
        }
        sync();

        return () => {
            cancelAnimationFrame(frame);
            document.removeEventListener('scroll', onScroll, {capture: true});
            window.removeEventListener('resize', schedule);
            observer.disconnect();
            element.style.minHeight = '';
        };
    }, []);
}
