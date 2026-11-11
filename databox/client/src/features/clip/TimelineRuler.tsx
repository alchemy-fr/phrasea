'use client';

import type {PointerEventHandler} from 'react';
import {cn} from '@alchemy/next-ui/utils/cn';
import {
    formatTickLabel,
    isInView,
    timeToPercent,
    type TimelineTicks,
    type TimelineView,
} from './timeline';

/**
 * Graduations of the part of the media shown, finer as it is zoomed in, the
 * major ones labelled. Pressed and dragged, it moves the playhead.
 */
export function TimelineRuler({
    view,
    ticks,
    currentTime,
    onPointerDown,
    onPointerMove,
    onPointerUp,
    onPointerCancel,
}: {
    view: TimelineView;
    ticks: TimelineTicks;
    currentTime: number;
    onPointerDown: PointerEventHandler<HTMLDivElement>;
    onPointerMove: PointerEventHandler<HTMLDivElement>;
    onPointerUp: PointerEventHandler<HTMLDivElement>;
    onPointerCancel: PointerEventHandler<HTMLDivElement>;
}) {
    return (
        <div
            className="relative h-5 cursor-col-resize touch-none overflow-hidden border-b select-none"
            data-testid="clip-timeline-ruler"
            aria-hidden
            onPointerDown={onPointerDown}
            onPointerMove={onPointerMove}
            onPointerUp={onPointerUp}
            onPointerCancel={onPointerCancel}
        >
            {ticks.ticks.map(tick => {
                const left = timeToPercent(tick.time, view);

                return (
                    <div
                        key={tick.time}
                        className="pointer-events-none absolute bottom-0"
                        style={{left: `${left}%`}}
                    >
                        <div
                            className={cn(
                                'w-px',
                                tick.major
                                    ? 'h-2.5 bg-foreground/60'
                                    : 'h-1.5 bg-foreground/30'
                            )}
                        />
                        {tick.major ? (
                            <span className="absolute bottom-2 left-1 font-mono text-[10px] leading-none whitespace-nowrap text-muted-foreground tabular-nums">
                                {formatTickLabel(tick.time, ticks.major)}
                            </span>
                        ) : null}
                    </div>
                );
            })}
            {isInView(view, currentTime) ? (
                <div
                    className="pointer-events-none absolute inset-y-0 w-px bg-foreground"
                    style={{left: `${timeToPercent(currentTime, view)}%`}}
                />
            ) : null}
        </div>
    );
}
