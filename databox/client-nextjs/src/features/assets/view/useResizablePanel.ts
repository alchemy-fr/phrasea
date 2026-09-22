'use client';

import {
    useCallback,
    useEffect,
    useState,
    type KeyboardEvent as ReactKeyboardEvent,
    type PointerEvent as ReactPointerEvent,
} from 'react';

/**
 * The side panel of the asset view has two uses with very different needs —
 * reading the information of the asset, and editing its attributes — so each
 * one is resized and remembered on its own. `info` keeps the historical key.
 */
export type PanelVariant = 'info' | 'edit';

const storageKeys: Record<PanelVariant, string> = {
    info: 'dbx.assetPanelWidth',
    edit: 'dbx.assetPanelWidth.edit',
};

const defaultWidths: Record<PanelVariant, number> = {
    info: 400,
    edit: 560,
};

const variants = Object.keys(storageKeys) as PanelVariant[];

const minWidth = 320;

function clamp(width: number): number {
    const max = Math.max(minWidth, Math.round(window.innerWidth * 0.7));

    return Math.min(max, Math.max(minWidth, Math.round(width)));
}

/**
 * Width of a panel docked on the right, dragged by its left edge and
 * remembered on this device, one width per variant.
 *
 * `onPointerDown` goes on the resize handle; the pointer is captured, so the
 * drag keeps working over the media and over iframes.
 */
export function useResizablePanel(variant: PanelVariant = 'info') {
    const [widths, setWidths] = useState(defaultWidths);
    const [resizing, setResizing] = useState(false);
    const width = widths[variant];

    // After hydration: the server render must not read the local storage
    useEffect(() => {
        setWidths(current => {
            const next = {...current};
            for (const v of variants) {
                const stored = Number(localStorage.getItem(storageKeys[v]));
                if (stored) {
                    next[v] = clamp(stored);
                }
            }

            return next;
        });
    }, []);

    // Only the displayed variant is written back, and only once the drag is
    // over: the others keep what they were loaded with
    useEffect(() => {
        if (resizing) {
            return;
        }
        try {
            localStorage.setItem(storageKeys[variant], String(width));
        } catch {
            // ignore (private mode)
        }
    }, [variant, width, resizing]);

    const resize = useCallback(
        (next: (previous: number) => number) =>
            setWidths(current => ({
                ...current,
                [variant]: clamp(next(current[variant])),
            })),
        [variant]
    );

    useEffect(() => {
        const onResize = () =>
            setWidths(
                current =>
                    Object.fromEntries(
                        variants.map(v => [v, clamp(current[v])])
                    ) as Record<PanelVariant, number>
            );
        window.addEventListener('resize', onResize);

        return () => window.removeEventListener('resize', onResize);
    }, []);

    const onPointerDown = useCallback(
        (e: ReactPointerEvent) => {
            e.preventDefault();
            const handle = e.currentTarget as HTMLElement;
            try {
                // Keeps the drag alive over the media and over iframes;
                // captured events still bubble up to the window
                handle.setPointerCapture(e.pointerId);
            } catch {
                // no such pointer (synthetic event)
            }
            setResizing(true);

            const move = (ev: PointerEvent) =>
                resize(() => window.innerWidth - ev.clientX);
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
        [resize]
    );

    /** The handle is focusable: arrow keys resize it too */
    const onKeyDown = useCallback(
        (e: ReactKeyboardEvent) => {
            const step = e.shiftKey ? 80 : 20;
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                resize(w => w + step);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                resize(w => w - step);
            }
        },
        [resize]
    );

    return {width, resizing, onPointerDown, onKeyDown};
}
