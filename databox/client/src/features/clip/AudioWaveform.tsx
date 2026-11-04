'use client';

import {useMemo} from 'react';
import {cn} from '@alchemy/next-ui/utils/cn';
import {waveformPath} from './waveform';

/**
 * Waveform of a sound, stretched to the size of its box: the background of
 * the clip timeline. Decorative.
 */
export function AudioWaveform({
    peaks,
    className,
}: {
    /** Values in [-1, 1], as exported by wavesurfer */
    peaks: number[];
    className?: string;
}) {
    const path = useMemo(() => waveformPath(peaks), [peaks]);

    return (
        <svg
            viewBox={`0 0 ${Math.max(1, peaks.length)} 2`}
            preserveAspectRatio="none"
            className={cn('block size-full text-primary/50', className)}
            aria-hidden
            data-testid="clip-waveform"
        >
            <path d={path} fill="currentColor" />
        </svg>
    );
}
