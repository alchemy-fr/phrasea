'use client';

import {
    useCallback,
    useEffect,
    useState,
    type PointerEvent as ReactPointerEvent,
} from 'react';

export type BatchEditorLayout = {
    /** height of the thumbnails band */
    thumbsHeight: number;
    /** width of the definitions column */
    definitionsWidth: number;
    /** width of the values / preview column */
    sideWidth: number;
    /** edge of the thumbnails */
    thumbSize: number;
};

const storageKey = 'dbx.batchEdit.layout';

export const thumbSizeRange = {min: 48, max: 320, step: 8};

/** thumbnails band padding + border, around one row of thumbnails */
const bandChrome = 17;

const defaults: BatchEditorLayout = {
    thumbsHeight: 160 + bandChrome,
    definitionsWidth: 256,
    sideWidth: 320,
    thumbSize: 160,
};

const bounds: Record<keyof BatchEditorLayout, [number, number]> = {
    thumbsHeight: [thumbSizeRange.min + bandChrome, 2000],
    definitionsWidth: [160, 800],
    sideWidth: [200, 1000],
    thumbSize: [thumbSizeRange.min, thumbSizeRange.max],
};

function clamp(key: keyof BatchEditorLayout, value: number): number {
    const [min, max] = bounds[key];

    return Math.min(max, Math.max(min, Math.round(value)));
}

/**
 * Sizes of the batch editor panes, resized by dragging their edges and
 * remembered on this device.
 */
export function useBatchEditorLayout() {
    const [layout, setLayout] = useState<BatchEditorLayout>(defaults);
    const [resizing, setResizing] = useState(false);

    // After hydration: the server render must not read the local storage
    useEffect(() => {
        try {
            const stored = JSON.parse(
                localStorage.getItem(storageKey) ?? '{}'
            ) as Partial<BatchEditorLayout>;
            setLayout(current => {
                const next = {...current};
                (Object.keys(defaults) as (keyof BatchEditorLayout)[]).forEach(
                    k => {
                        if (typeof stored[k] === 'number') {
                            next[k] = clamp(k, stored[k]);
                        }
                    }
                );

                return next;
            });
        } catch {
            // ignore (private mode, corrupted value)
        }
    }, []);

    useEffect(() => {
        if (resizing) {
            return;
        }
        try {
            localStorage.setItem(storageKey, JSON.stringify(layout));
        } catch {
            // ignore (private mode)
        }
    }, [layout, resizing]);

    const set = useCallback(
        (key: keyof BatchEditorLayout, value: number) =>
            setLayout(current => {
                const next = {...current, [key]: clamp(key, value)};
                // Zooming in grows the band so that a row stays visible
                if (key === 'thumbSize') {
                    next.thumbsHeight = Math.max(
                        next.thumbsHeight,
                        next.thumbSize + bandChrome
                    );
                }

                return next;
            }),
        []
    );

    /**
     * Pointer-down handler of a resize handle. `sign` is 1 when dragging
     * towards the right / bottom grows the pane, -1 otherwise.
     */
    const startResize = useCallback(
        (key: keyof BatchEditorLayout, axis: 'x' | 'y', sign: 1 | -1) =>
            (e: ReactPointerEvent) => {
                e.preventDefault();
                const handle = e.currentTarget as HTMLElement;
                try {
                    handle.setPointerCapture(e.pointerId);
                } catch {
                    // no such pointer (synthetic event)
                }
                const origin = axis === 'x' ? e.clientX : e.clientY;
                const initial = layout[key];
                setResizing(true);

                const move = (ev: PointerEvent) =>
                    set(
                        key,
                        initial +
                            sign *
                                ((axis === 'x' ? ev.clientX : ev.clientY) -
                                    origin)
                    );
                const up = () => {
                    window.removeEventListener('pointermove', move);
                    window.removeEventListener('pointerup', up);
                    window.removeEventListener('pointercancel', up);
                    setResizing(false);
                };
                window.addEventListener('pointermove', move);
                window.addEventListener('pointerup', up);
                window.addEventListener('pointercancel', up);
            },
        [layout, set]
    );

    return {layout, set, startResize, resizing};
}
