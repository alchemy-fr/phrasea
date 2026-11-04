'use client';

import {
    useCallback,
    useEffect,
    useRef,
    useState,
    type KeyboardEvent as ReactKeyboardEvent,
    type PointerEvent as ReactPointerEvent,
} from 'react';

/**
 * Each resizable panel keeps its own width. The side panel of the asset view
 * has two uses with very different needs — reading the information of the
 * asset (`info`, which keeps the historical key), and editing its attributes
 * (`edit`) — so each one is resized and remembered on its own. `workflow-job`
 * is the details of the job selected in a workflow graph, `left-panel` the
 * sidebar of the app and `quarantine` the queue of the quarantine screen.
 */
export type PanelVariant =
    | 'info'
    | 'edit'
    | 'workflow-job'
    | 'left-panel'
    | 'quarantine';

type PanelConfig = {
    storageKey: string;
    defaultWidth: number;
    minWidth: number;
    /** Share of the window the panel can take at most */
    maxRatio: number;
    /** The edge of the window the panel is docked on: its handle is on the other one */
    side: 'left' | 'right';
};

const configs: Record<PanelVariant, PanelConfig> = {
    'info': {
        storageKey: 'dbx.assetPanelWidth',
        defaultWidth: 400,
        minWidth: 320,
        maxRatio: 0.7,
        side: 'right',
    },
    'edit': {
        storageKey: 'dbx.assetPanelWidth.edit',
        defaultWidth: 560,
        minWidth: 320,
        maxRatio: 0.7,
        side: 'right',
    },
    'workflow-job': {
        storageKey: 'dbx.workflowJobPanelWidth',
        defaultWidth: 448,
        minWidth: 320,
        maxRatio: 0.7,
        side: 'right',
    },
    'left-panel': {
        storageKey: 'dbx.leftPanelWidth',
        defaultWidth: 300,
        minWidth: 220,
        maxRatio: 0.5,
        side: 'left',
    },
    'quarantine': {
        storageKey: 'dbx.quarantineQueueWidth',
        defaultWidth: 280,
        minWidth: 200,
        maxRatio: 0.5,
        side: 'left',
    },
};

const variants = Object.keys(configs) as PanelVariant[];

const defaultWidths = Object.fromEntries(
    variants.map(v => [v, configs[v].defaultWidth])
) as Record<PanelVariant, number>;

function clamp(variant: PanelVariant, width: number): number {
    const {minWidth, maxRatio} = configs[variant];
    const max = Math.max(minWidth, Math.round(window.innerWidth * maxRatio));

    return Math.min(max, Math.max(minWidth, Math.round(width)));
}

/**
 * Width of a panel dragged by its inner edge (the left one of a panel docked
 * on the right, and the other way round) and remembered on this device, one
 * width per variant.
 *
 * `onPointerDown` goes on the resize handle; the pointer is captured, so the
 * drag keeps working over the media and over iframes.
 */
export function useResizablePanel(variant: PanelVariant = 'info') {
    const [widths, setWidths] = useState(defaultWidths);
    const [loaded, setLoaded] = useState(false);
    const [resizing, setResizing] = useState(false);
    const width = widths[variant];
    // Growing the panel moves its handle away from the edge it is docked on
    const direction = configs[variant].side === 'left' ? 1 : -1;
    const widthRef = useRef(width);
    widthRef.current = width;

    // After hydration: the server render must not read the local storage.
    // Read right away, not in the state updater: that one only runs at the
    // next render, after the default width would have been written back.
    useEffect(() => {
        const stored: Partial<Record<PanelVariant, number>> = {};
        for (const v of variants) {
            try {
                const width = Number(
                    localStorage.getItem(configs[v].storageKey)
                );
                if (width) {
                    stored[v] = clamp(v, width);
                }
            } catch {
                // storage unavailable
            }
        }
        setWidths(current => ({...current, ...stored}));
        setLoaded(true);
    }, []);

    // Only the displayed variant is written back, once loaded and once the
    // drag is over: the others keep what they were loaded with
    useEffect(() => {
        if (!loaded || resizing) {
            return;
        }
        try {
            localStorage.setItem(configs[variant].storageKey, String(width));
        } catch {
            // ignore (private mode)
        }
    }, [variant, width, loaded, resizing]);

    const resize = useCallback(
        (next: (previous: number) => number) =>
            setWidths(current => ({
                ...current,
                [variant]: clamp(variant, next(current[variant])),
            })),
        [variant]
    );

    useEffect(() => {
        const onResize = () =>
            setWidths(
                current =>
                    Object.fromEntries(
                        variants.map(v => [v, clamp(v, current[v])])
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

            // Relative to where the drag started: the panel is not
            // necessarily against the edge of the window
            const startX = e.clientX;
            const startWidth = widthRef.current;
            const move = (ev: PointerEvent) =>
                resize(() => startWidth + (ev.clientX - startX) * direction);
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
        [resize, direction]
    );

    /** The handle is focusable: arrow keys resize it too */
    const onKeyDown = useCallback(
        (e: ReactKeyboardEvent) => {
            const step = e.shiftKey ? 80 : 20;
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                resize(w => w - step * direction);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                resize(w => w + step * direction);
            }
        },
        [resize, direction]
    );

    /** `loaded`: the width remembered on this device is known */
    return {width, loaded, resizing, onPointerDown, onKeyDown};
}
