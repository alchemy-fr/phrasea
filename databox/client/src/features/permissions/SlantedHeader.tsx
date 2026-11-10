import {
    ComponentProps,
    DependencyList,
    MouseEvent,
    useCallback,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import {cn} from '@alchemy/next-ui/utils/cn';

/**
 * Table with slanted headers: the paint containment clips the bands at its
 * right edge, where they would otherwise widen the scrollable area
 */
export const slantedTableClassName = 'w-full text-sm [contain:paint]';

/** Width of a permission column: a checkbox under a slanted label */
export const slantedColumnClassName =
    'relative w-10 min-w-10 p-0 align-bottom font-medium';

/** Body cell of a permission column, delimited like its slanted header */
export const slantedCellClassName = 'border-l px-2 py-1.5 text-center';

const highlightClassName = 'bg-primary/10';

const labelSelector = '[data-slanted-label]';
const endSelector = '[data-slanted-end]';
const gap = 8;

/**
 * The slanted labels are absolutely positioned, so the table does not make
 * room for them: the header row gets the height of the tallest one, and the
 * last column ([data-slanted-end]) the width of their overflow on the right.
 * The bands are not measured: they are clipped (see slantedTableClassName).
 */
export function useSlantedHeader(deps: DependencyList) {
    const ref = useRef<HTMLTableRowElement>(null);

    const measure = useCallback(() => {
        const row = ref.current;
        const end = row?.querySelector<HTMLElement>(endSelector);
        if (!row || !end) {
            return;
        }

        const rowRect = row.getBoundingClientRect();
        // The rects are scaled by the transforms of the ancestors (dialog opening)
        const scale = row.offsetWidth / rowRect.width || 1;
        const endLeft = end.getBoundingClientRect().left;
        let height = 0;
        let width = 0;
        row.querySelectorAll(labelSelector).forEach(label => {
            const rect = label.getBoundingClientRect();
            height = Math.max(height, rowRect.bottom - rect.top);
            width = Math.max(width, rect.right - endLeft);
        });

        row.style.height = `${Math.ceil(height * scale + gap)}px`;
        end.style.width =
            end.style.minWidth = `${Math.ceil(width * scale + gap)}px`;
    }, []);

    useLayoutEffect(() => {
        measure();
        let cancelled = false;
        void document.fonts?.ready.then(() => !cancelled && measure());

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, deps);

    return ref;
}

/** Column header read upwards to the right, so that many permissions fit */
export function SlantedLabel({className, ...props}: ComponentProps<'span'>) {
    return (
        <span
            data-slanted-label
            className={cn(
                // Its middle line starts from the bottom of the column, at its
                // center, like the middle line of the band
                'absolute bottom-0 left-1/2 z-10 origin-left translate-y-1/2 -rotate-45 pl-3 text-sm whitespace-nowrap',
                className
            )}
            {...props}
        />
    );
}

/**
 * Parallelogram leaning like the labels, prolonging the column borders in the
 * header. Also put in the last column, to close the last permission column.
 */
export function SlantedBand({
    highlighted,
    className,
    ...props
}: {highlighted?: boolean} & ComponentProps<'div'>) {
    return (
        <div
            data-slanted-band
            aria-hidden
            className={cn(
                'absolute inset-y-0 left-0 w-full origin-bottom-left -skew-x-45 border-l',
                highlighted && highlightClassName,
                className
            )}
            {...props}
        />
    );
}

/**
 * Highlights the column under the mouse: spread `tableProps` on the table and
 * add `highlight(cellIndex)` to the class of the cells to highlight.
 */
export function useColumnHover() {
    const [column, setColumn] = useState<number>();

    const onMouseOver = (e: MouseEvent<HTMLTableElement>) => {
        const cell = (e.target as Element).closest<HTMLTableCellElement>(
            'td, th'
        );
        // Events of the portals (tooltips) bubble through the table too
        setColumn(
            cell && cell.colSpan === 1 && e.currentTarget.contains(cell)
                ? cell.cellIndex
                : undefined
        );
    };

    return {
        column,
        highlight: (index: number) =>
            index === column ? highlightClassName : undefined,
        tableProps: {
            onMouseOver,
            onMouseLeave: () => setColumn(undefined),
        },
    };
}
