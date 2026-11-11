'use client';

import {
    type RefObject,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import {
    clampView,
    followPlayhead,
    fullView,
    getMinSpan,
    isInView,
    panView,
    type TimelineView,
    zoomView,
} from './timeline';

/** Wheel distance (px) doubling or halving the zoom */
const WHEEL_ZOOM_PX = 150;

/**
 * The part of the media shown by the timeline: the whole of it at first,
 * zoomed down to a frame `framePx` wide over the width of `laneRef`.
 * Ctrl (⌘) + wheel, or a pinch, zooms around the cursor; a horizontal wheel
 * (shift + wheel) scrolls; both on `rootRef`. When the playhead moves out of
 * the view, the view follows it page by page.
 */
export function useTimelineView({
    rootRef,
    laneRef,
    duration,
    frameDuration,
    framePx,
    currentTime,
}: {
    rootRef: RefObject<HTMLElement | null>;
    laneRef: RefObject<HTMLElement | null>;
    duration: number;
    frameDuration: number;
    framePx: number;
    currentTime: number;
}) {
    const [width, setWidth] = useState(0);

    useLayoutEffect(() => {
        const el = laneRef.current;
        if (!el) {
            return;
        }
        setWidth(el.getBoundingClientRect().width);
        if (typeof ResizeObserver === 'undefined') {
            return;
        }
        const observer = new ResizeObserver(([entry]) =>
            setWidth(entry.contentRect.width)
        );
        observer.observe(el);

        return () => observer.disconnect();
    }, [laneRef]);

    const minSpan = getMinSpan(width, frameDuration, framePx);
    const [state, setState] = useState(() => ({
        duration,
        view: fullView(duration),
    }));
    // A new media: the whole of it
    const current = (s: typeof state) =>
        clampView(
            s.duration === duration ? s.view : fullView(duration),
            duration,
            minSpan
        );
    const view = current(state);
    const updateView = (update: (view: TimelineView) => TimelineView) =>
        setState(s => ({
            duration,
            view: clampView(update(current(s)), duration, minSpan),
        }));

    const [followed, setFollowed] = useState(currentTime);
    if (followed !== currentTime) {
        setFollowed(currentTime);
        const next = followPlayhead(view, currentTime, duration);
        if (next !== view) {
            setState({duration, view: next});
        }
    }

    /** Around `anchor`, by default the playhead when shown, the middle otherwise */
    const zoom = (factor: number, anchor?: number) =>
        updateView(v =>
            zoomView(
                v,
                factor,
                anchor ??
                    (isInView(v, currentTime)
                        ? currentTime
                        : v.start + v.span / 2),
                duration,
                minSpan
            )
        );
    const fit = () => updateView(() => fullView(duration));
    const scrollTo = (start: number) =>
        updateView(v => panView(v, start - v.start, duration));

    // A native listener: React listens to the wheel passively
    const wheelRef = useRef<(e: WheelEvent) => void>(undefined);
    wheelRef.current = (e: WheelEvent) => {
        const rect = laneRef.current?.getBoundingClientRect();
        if (!rect || !(rect.width > 0)) {
            return;
        }
        const scale =
            e.deltaMode === 1 ? 16 : e.deltaMode === 2 ? rect.width : 1;
        if (e.ctrlKey || e.metaKey) {
            // Also a pinch on a trackpad
            e.preventDefault();
            zoom(
                2 ** ((-e.deltaY * scale) / WHEEL_ZOOM_PX),
                view.start + ((e.clientX - rect.left) / rect.width) * view.span
            );
        } else if (e.shiftKey || Math.abs(e.deltaX) > Math.abs(e.deltaY)) {
            const delta =
                (e.shiftKey && !e.deltaX ? e.deltaY : e.deltaX) * scale;
            if (delta) {
                e.preventDefault();
                updateView(v =>
                    panView(v, (delta / rect.width) * v.span, duration)
                );
            }
        }
    };
    useEffect(() => {
        const el = rootRef.current;
        if (!el) {
            return;
        }
        const listener = (e: WheelEvent) => wheelRef.current?.(e);
        el.addEventListener('wheel', listener, {passive: false});

        return () => el.removeEventListener('wheel', listener);
    }, [rootRef]);

    return {width, minSpan, view, zoom, fit, scrollTo};
}
