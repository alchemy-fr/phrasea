'use client';

import {type PointerEvent, useRef} from 'react';
import type {ClipTimeRange} from './types';
import type {TimelineView} from './timeline';

/**
 * The whole duration of the media, its ranges drawn as thin lines, and the
 * part shown by the timeline: dragging that window scrolls the timeline, a
 * press elsewhere centers it there.
 */
export function TimelineOverview({
    duration,
    view,
    ranges,
    selected,
    currentTime,
    label,
    onViewStart,
}: {
    duration: number;
    view: TimelineView;
    ranges: ClipTimeRange[];
    selected: number;
    currentTime: number;
    label: string;
    /** Moves the start of the view, in seconds */
    onViewStart: (start: number) => void;
}) {
    // Where the window was grabbed, from its start, in seconds
    const grab = useRef<number | undefined>(undefined);
    const percent = (time: number) =>
        duration > 0 ? Math.min(100, Math.max(0, (time / duration) * 100)) : 0;

    const timeAt = (e: PointerEvent<HTMLDivElement>) => {
        const rect = e.currentTarget.getBoundingClientRect();

        return rect.width > 0
            ? ((e.clientX - rect.left) / rect.width) * duration
            : 0;
    };

    const onPointerDown = (e: PointerEvent<HTMLDivElement>) => {
        if (e.button !== 0) {
            return;
        }
        e.preventDefault();
        e.currentTarget.setPointerCapture?.(e.pointerId);
        const time = timeAt(e);
        const inWindow = time >= view.start && time <= view.start + view.span;
        grab.current = inWindow ? time - view.start : view.span / 2;
        if (!inWindow) {
            onViewStart(time - grab.current);
        }
    };

    const onPointerMove = (e: PointerEvent<HTMLDivElement>) => {
        if (grab.current !== undefined) {
            onViewStart(timeAt(e) - grab.current);
        }
    };

    const onPointerUp = () => {
        grab.current = undefined;
    };

    return (
        <div
            className="relative h-3 cursor-grab touch-none rounded-sm bg-muted select-none active:cursor-grabbing"
            data-testid="clip-timeline-overview"
            aria-hidden
            title={label}
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerUp}
            onPointerCancel={onPointerUp}
        >
            {ranges.map((r, i) => (
                <div
                    // The ranges are edited in place: their index is their identity
                    key={i}
                    className={
                        i === selected
                            ? 'pointer-events-none absolute top-1/2 h-1 -translate-y-1/2 rounded-full bg-primary'
                            : 'pointer-events-none absolute top-1/2 h-0.5 -translate-y-1/2 rounded-full bg-primary/60'
                    }
                    style={{
                        left: `${percent(r.s)}%`,
                        width: `${Math.max(0.3, percent(r.e) - percent(r.s))}%`,
                    }}
                />
            ))}
            <div
                className="pointer-events-none absolute inset-y-0 w-px bg-foreground/70"
                style={{left: `${percent(currentTime)}%`}}
            />
            <div
                className="pointer-events-none absolute inset-y-0 rounded-sm border border-foreground/70 bg-foreground/10"
                style={{
                    left: `${percent(view.start)}%`,
                    width: `${Math.max(0.5, percent(view.start + view.span) - percent(view.start))}%`,
                }}
                data-testid="clip-timeline-window"
            />
        </div>
    );
}
