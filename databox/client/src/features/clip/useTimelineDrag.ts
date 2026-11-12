'use client';

import {type PointerEvent, type RefObject, useRef, useState} from 'react';
import type {ClipTimeRange} from './types';
import {snapToFrame, type TimelineView, xToTime} from './timeline';

/** Distance below which a press is a click, not a drag, in px */
export const DRAG_THRESHOLD = 4;

type DragMode =
    /** Pressed on the background: a click, or a new range */
    | 'pending'
    | 'create'
    /** Moves the playhead */
    | 'scrub'
    /** Pressed on a range: a click selects it, a drag moves it */
    | 'range'
    | 'move'
    | 'start'
    | 'end';

type Drag = {
    mode: DragMode;
    pointerId: number;
    originX: number;
    originTime: number;
    index: number;
    /** The range as it was when pressed */
    range?: ClipTimeRange;
};

export type TimelineDragOptions = {
    /** The element the times are measured on, as wide as the track */
    laneRef: RefObject<HTMLElement | null>;
    view: TimelineView;
    duration: number;
    frameDuration: number;
    ranges: ClipTimeRange[];
    selected: number;
    canCreate: boolean;
    onChange: (index: number, range: ClipTimeRange) => void;
    onSelect: (index: number) => void;
    onSeek: (time: number) => void;
    onCreate: (range: ClipTimeRange) => void;
    /** The time under the cursor out of a drag, undefined when it leaves */
    onHover?: (time: number | undefined) => void;
};

const clamp = (value: number, min: number, max: number) =>
    Math.min(max, Math.max(min, value));

/**
 * What the pointer does on the timeline: on the background, a click moves
 * the playhead and a drag draws a new range; on a range, a click selects it
 * and a drag moves it (its length kept); its handles move its start or end;
 * on the ruler, a drag moves the playhead. Every time is snapped to a frame.
 *
 * The element pressed tells what it is with `data-drag` (`range`, `start`,
 * `end`) and `data-index`.
 */
export function useTimelineDrag({
    laneRef,
    view,
    duration,
    frameDuration,
    ranges,
    selected,
    canCreate,
    onChange,
    onSelect,
    onSeek,
    onCreate,
    onHover,
}: TimelineDragOptions) {
    const drag = useRef<Drag | undefined>(undefined);
    const [draft, setDraft] = useState<ClipTimeRange>();
    const [dragging, setDragging] = useState(false);

    const snap = (time: number) =>
        clamp(snapToFrame(time, frameDuration), 0, duration);

    const timeAt = (clientX: number) => {
        const rect = laneRef.current?.getBoundingClientRect();
        if (!rect || !(rect.width > 0)) {
            return view.start;
        }

        return clamp(
            xToTime(clientX - rect.left, view, rect.width),
            0,
            duration
        );
    };

    const start = (e: PointerEvent<HTMLElement>, scrub = false) => {
        if (e.button !== 0 || drag.current) {
            return;
        }
        const target = (e.target as HTMLElement).closest<HTMLElement>(
            '[data-drag]'
        );
        const kind = scrub ? undefined : target?.dataset.drag;
        const index = Number(target?.dataset.index ?? -1);
        const time = timeAt(e.clientX);
        let mode: DragMode = scrub ? 'scrub' : 'pending';
        if (kind === 'start' || kind === 'end') {
            mode = kind;
        } else if (kind === 'range') {
            mode = 'range';
        }

        e.preventDefault();
        e.currentTarget.setPointerCapture?.(e.pointerId);
        drag.current = {
            mode,
            pointerId: e.pointerId,
            originX: e.clientX,
            originTime: time,
            index,
            range: index >= 0 ? ranges[index] : undefined,
        };
        setDragging(true);
        onHover?.(undefined);
        if (mode === 'scrub') {
            onSeek(snap(time));
        }
    };

    const move = (e: PointerEvent<HTMLElement>) => {
        const d = drag.current;
        if (!d) {
            if (e.pointerType !== 'touch') {
                onHover?.(timeAt(e.clientX));
            }

            return;
        }
        if (e.pointerId !== d.pointerId) {
            return;
        }
        const time = timeAt(e.clientX);
        const far = Math.abs(e.clientX - d.originX) > DRAG_THRESHOLD;
        if (d.mode === 'pending' && far) {
            d.mode = canCreate ? 'create' : 'scrub';
        } else if (d.mode === 'range' && far) {
            d.mode = 'move';
            if (d.index !== selected) {
                onSelect(d.index);
            }
        }
        const {range, index} = d;

        switch (d.mode) {
            case 'create': {
                const s = snap(Math.min(d.originTime, time));
                const end = snap(Math.max(d.originTime, time));
                setDraft({s, e: end});
                break;
            }
            case 'scrub':
                onSeek(snap(time));
                break;
            case 'start':
                if (range) {
                    const s = clamp(
                        snap(time),
                        0,
                        Math.max(0, range.e - frameDuration)
                    );
                    onChange(index, {s, e: range.e});
                    onSeek(s);
                }
                break;
            case 'end':
                if (range) {
                    const end = clamp(
                        snap(time),
                        Math.min(duration, range.s + frameDuration),
                        duration
                    );
                    onChange(index, {s: range.s, e: end});
                    // The last frame of the range, still within it
                    onSeek(Math.max(range.s, snap(end - frameDuration)));
                }
                break;
            case 'move':
                if (range) {
                    const length = range.e - range.s;
                    const s = clamp(
                        snapToFrame(
                            range.s + time - d.originTime,
                            frameDuration
                        ),
                        0,
                        Math.max(0, duration - length)
                    );
                    onChange(index, {s, e: s + length});
                    onSeek(s);
                }
                break;
        }
    };

    const end = (e: PointerEvent<HTMLElement>, cancel = false) => {
        const d = drag.current;
        if (!d || e.pointerId !== d.pointerId) {
            return;
        }
        drag.current = undefined;
        setDragging(false);
        setDraft(undefined);
        if (cancel) {
            return;
        }
        if (d.mode === 'pending') {
            onSeek(snap(timeAt(e.clientX)));
        } else if (d.mode === 'range') {
            onSelect(d.index);
        } else if (d.mode === 'create') {
            const time = timeAt(e.clientX);
            const s = snap(Math.min(d.originTime, time));
            const last = snap(Math.max(d.originTime, time));
            if (last > s) {
                onCreate({s, e: last});
            }
        }
    };

    return {
        draft,
        dragging,
        handlers: {
            onPointerDown: (e: PointerEvent<HTMLElement>) => start(e),
            onPointerMove: move,
            onPointerUp: (e: PointerEvent<HTMLElement>) => end(e),
            onPointerCancel: (e: PointerEvent<HTMLElement>) => end(e, true),
            onPointerLeave: () => {
                if (!drag.current) {
                    onHover?.(undefined);
                }
            },
        },
        rulerHandlers: {
            onPointerDown: (e: PointerEvent<HTMLElement>) => start(e, true),
            onPointerMove: move,
            onPointerUp: (e: PointerEvent<HTMLElement>) => end(e),
            onPointerCancel: (e: PointerEvent<HTMLElement>) => end(e, true),
        },
    };
}
