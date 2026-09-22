'use client';

import {
    useCallback,
    useEffect,
    useState,
    type KeyboardEvent as ReactKeyboardEvent,
    type PointerEvent as ReactPointerEvent,
} from 'react';

const storageKey = 'dbx.assetPanelWidth';
const minWidth = 320;
const defaultWidth = 400;

function clamp(width: number): number {
    const max = Math.max(minWidth, Math.round(window.innerWidth * 0.7));

    return Math.min(max, Math.max(minWidth, Math.round(width)));
}

/**
 * Width of a panel docked on the right, dragged by its left edge and
 * remembered on this device.
 *
 * `onPointerDown` goes on the resize handle; the pointer is captured, so the
 * drag keeps working over the media and over iframes.
 */
export function useResizablePanel() {
    const [width, setWidth] = useState(defaultWidth);
    const [resizing, setResizing] = useState(false);

    // After hydration: the server render must not read the local storage
    useEffect(() => {
        const stored = Number(localStorage.getItem(storageKey));
        if (stored) {
            setWidth(clamp(stored));
        }
    }, []);

    useEffect(() => {
        if (resizing) {
            return;
        }
        try {
            localStorage.setItem(storageKey, String(width));
        } catch {
            // ignore (private mode)
        }
    }, [width, resizing]);

    useEffect(() => {
        const onResize = () => setWidth(w => clamp(w));
        window.addEventListener('resize', onResize);

        return () => window.removeEventListener('resize', onResize);
    }, []);

    const onPointerDown = useCallback((e: ReactPointerEvent) => {
        e.preventDefault();
        const handle = e.currentTarget as HTMLElement;
        try {
            // Keeps the drag alive over the media and over iframes; captured
            // events still bubble up to the window
            handle.setPointerCapture(e.pointerId);
        } catch {
            // no such pointer (synthetic event)
        }
        setResizing(true);

        const move = (ev: PointerEvent) =>
            setWidth(clamp(window.innerWidth - ev.clientX));
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            window.removeEventListener('pointercancel', up);
            setResizing(false);
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
        window.addEventListener('pointercancel', up);
    }, []);

    /** The handle is focusable: arrow keys resize it too */
    const onKeyDown = useCallback((e: ReactKeyboardEvent) => {
        const step = e.shiftKey ? 80 : 20;
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            setWidth(w => clamp(w + step));
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            setWidth(w => clamp(w - step));
        }
    }, []);

    return {width, resizing, onPointerDown, onKeyDown};
}
