'use client';

import {CSSProperties, ReactNode, useEffect, useState} from 'react';
import {createPortal} from 'react-dom';
import {
    ClientRect,
    DragOverlay,
    DropAnimation,
    getClientRect,
    MeasuringConfiguration,
    useDndContext,
} from '@dnd-kit/core';
import {useSortable} from '@dnd-kit/sortable';
import {CSS} from '@dnd-kit/utilities';

/**
 * How a row of a sortable list is wired to the drag: spread `nodeRef` and
 * `style` on the row element, `handle` on its grip, and add `className`.
 * The copy following the pointer (see {@link SortableOverlay}) renders the
 * same row with {@link overlayRow}.
 */
export type SortableRow = {
    nodeRef?: (el: HTMLElement | null) => void;
    style?: CSSProperties;
    handle?: Record<string, unknown>;
    className?: string;
};

export function useSortableRow(id: string, disabled = false): SortableRow {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({id, disabled});

    return {
        nodeRef: setNodeRef,
        style: {transform: CSS.Transform.toString(transform), transition},
        handle: {...attributes, ...listeners},
        // Only marks where the row will land: its copy follows the pointer
        className: isDragging ? 'opacity-40' : undefined,
    };
}

/** Marks the overlay: see {@link measureOverlay} */
const overlayClass = 'sortable-overlay';

/**
 * To pass to the `DndContext` of a list using {@link SortableOverlay}.
 *
 * dnd-kit measures the copy once rendered, inside an overlay the drag has
 * already moved, then adds the whole drag to that rect: the drop target
 * would be off by the distance covered before the copy showed up. Measured
 * where the overlay started instead.
 */
export const sortableMeasuring: MeasuringConfiguration = {
    dragOverlay: {measure: measureOverlay},
};

function measureOverlay(node: HTMLElement): ClientRect {
    const rect = getClientRect(node);
    const overlay = node.closest<HTMLElement>(`.${overlayClass}`);
    const transform = overlay ? getComputedStyle(overlay).transform : 'none';
    if (transform === 'none') {
        return rect;
    }
    const {m41: x, m42: y} = new DOMMatrixReadOnly(transform);

    return {
        ...rect,
        top: rect.top - y,
        bottom: rect.bottom - y,
        left: rect.left - x,
        right: rect.right - x,
    };
}

/**
 * The default drop animation, its target corrected: the overlay is measured
 * where it started (see {@link measureOverlay}), not where the drop leaves
 * it, so dnd-kit would add the whole drag to the way back — the copy flew
 * past the landing place before snapping onto it.
 */
const dropAnimation: DropAnimation = {
    keyframes: ({transform: {initial, final}, active, dragOverlay}) => [
        {transform: CSS.Transform.toString(initial)},
        {
            transform: CSS.Transform.toString({
                ...final,
                x: active.rect.left - dragOverlay.rect.left,
                y: active.rect.top - dragOverlay.rect.top,
            }),
        },
    ],
};

export const overlayRow: SortableRow = {
    className: 'cursor-grabbing shadow-lg',
};

/**
 * The copy of the dragged row following the pointer. Rendered on the body:
 * moving the row itself would clip it to the scrolling list, dialog or
 * popover it lives in.
 */
export function SortableOverlay({
    children,
}: {
    children: (id: string) => ReactNode;
}) {
    const {active} = useDndContext();
    // No body to render into on the server
    const [mounted, setMounted] = useState(false);
    useEffect(() => setMounted(true), []);

    if (!mounted) {
        return null;
    }

    return createPortal(
        // Above the dialogs and popovers the list may live in
        <DragOverlay
            zIndex={60}
            className={overlayClass}
            dropAnimation={dropAnimation}
        >
            {active ? children(String(active.id)) : null}
        </DragOverlay>,
        document.body
    );
}
