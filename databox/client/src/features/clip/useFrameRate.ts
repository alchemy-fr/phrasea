'use client';

import {useEffect, useState} from 'react';
import {DEFAULT_FRAME_RATE, measureFrameRate} from './frameRate';

export type FrameRateState = {
    /** Measured on the video, undefined while measuring or when it failed */
    measured?: number;
    measuring: boolean;
    /** Chosen by the user instead of the measure */
    override?: number;
    /** The override, the measure, or `DEFAULT_FRAME_RATE` */
    frameRate: number;
    /** Undefined goes back to the measure */
    setOverride: (frameRate: number | undefined) => void;
};

/**
 * Frame rate of the video played at `src`, measured in the browser (see
 * `measureFrameRate`); the user may choose another one. Nothing is measured
 * without `src` (a sound).
 */
export function useFrameRate(src: string | undefined): FrameRateState {
    const [result, setResult] = useState<{src: string; fps?: number}>();
    const [override, setOverride] = useState<number>();

    useEffect(() => {
        if (!src) {
            return;
        }
        const controller = new AbortController();
        void measureFrameRate(src, controller.signal).then(fps => {
            if (!controller.signal.aborted) {
                setResult({src, fps});
            }
        });

        return () => controller.abort();
    }, [src]);

    const measured = src && result?.src === src ? result.fps : undefined;

    return {
        measured,
        measuring: !!src && result?.src !== src,
        override,
        frameRate: override ?? measured ?? DEFAULT_FRAME_RATE,
        setOverride,
    };
}
