'use client';

import {useMemo} from 'react';
import {cn} from '@alchemy/next-ui/utils/cn';
import {waveformPath} from './waveform';

/**
 * Waveform of a sound, stretched to the size of its box: the background of
 * the clip timeline. Decorative.
 *
 * With `view` and `duration`, only the part of the sound in the view is
 * shown, stretched in the same way (coarse when zoomed in a lot: the peaks
 * are only so many).
 */
export function AudioWaveform({
    peaks,
    view,
    duration,
    className,
}: {
    /** Values in [-1, 1], as exported by wavesurfer */
    peaks: number[];
    /** The part shown, in seconds */
    view?: {start: number; span: number};
    duration?: number;
    className?: string;
}) {
    const path = useMemo(() => waveformPath(peaks), [peaks]);
    const count = Math.max(1, peaks.length);
    const windowed = view && duration && duration > 0 && view.span > 0;
    const x = windowed ? (view.start / duration) * count : 0;
    const width = windowed ? (view.span / duration) * count : count;

    return (
        <svg
            viewBox={`${x} 0 ${width} 2`}
            preserveAspectRatio="none"
            className={cn('block size-full text-primary/50', className)}
            aria-hidden
            data-testid="clip-waveform"
        >
            <path d={path} fill="currentColor" />
        </svg>
    );
}
