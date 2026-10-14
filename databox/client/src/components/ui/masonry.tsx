'use client';

import {ReactNode, useLayoutEffect, useMemo, useRef, useState} from 'react';
import {cn} from '@/lib/utils/cn';

type Props<T> = {
    items: T[];
    /** Minimum width of a column, like `minmax(columnWidth, 1fr)` in a grid */
    columnWidth: number;
    /** Space between columns and between items, in pixels */
    gap?: number;
    getKey: (item: T, index: number) => string;
    renderItem: (item: T, index: number) => ReactNode;
    className?: string;
};

/**
 * Masonry layout: as many columns as fit (at least `columnWidth` wide each),
 * items keeping their own height.
 *
 * Items are dealt to the columns in turn (item `i` goes to column
 * `i % count`): the reading order stays row by row, and appending items never
 * moves the ones already displayed. Balancing on the shortest column would
 * need the item heights, unknown until the images are loaded.
 */
export function Masonry<T>({
    items,
    columnWidth,
    gap = 12,
    getKey,
    renderItem,
    className,
}: Props<T>) {
    const ref = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(0);

    useLayoutEffect(() => {
        const el = ref.current;
        if (!el) {
            return;
        }
        setWidth(el.clientWidth);
        if (typeof ResizeObserver === 'undefined') {
            return;
        }
        const observer = new ResizeObserver(([entry]) =>
            setWidth(entry.contentRect.width)
        );
        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    const count = columnCount(width, columnWidth, gap);
    const columns = useMemo(() => {
        const cols: {item: T; index: number}[][] = Array.from(
            {length: count},
            () => []
        );
        items.forEach((item, index) => cols[index % count].push({item, index}));

        return cols;
    }, [items, count]);

    return (
        <div
            ref={ref}
            data-testid="masonry"
            className={cn('flex items-start', className)}
            style={{gap}}
        >
            {columns.map((col, c) => (
                <div
                    key={c}
                    className="flex min-w-0 flex-1 flex-col"
                    style={{gap}}
                >
                    {col.map(({item, index}) => (
                        <div key={getKey(item, index)}>
                            {renderItem(item, index)}
                        </div>
                    ))}
                </div>
            ))}
        </div>
    );
}

/** Number of columns of at least `columnWidth` fitting in `width` */
export function columnCount(
    width: number,
    columnWidth: number,
    gap: number
): number {
    return Math.max(1, Math.floor((width + gap) / (columnWidth + gap)));
}
