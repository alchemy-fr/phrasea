'use client';

import {type RefObject, useEffect} from 'react';
import type {ClipTimeRange} from './types';
import {isInRange} from './clip';

/**
 * Bounds the playback of a media element to a time range: it starts at `s`
 * once the metadata is loaded, is brought back into `[s, e)` when played or
 * sought outside of it, and stops (or loops, with `loop`) at `e`.
 *
 * `timeupdate` only fires a few times per second: the end is also watched on
 * every frame while playing, so that nothing past `e` is shown.
 */
export function useClipPlayback(
    ref: RefObject<HTMLMediaElement | null>,
    range: ClipTimeRange | undefined,
    {loop = false}: {loop?: boolean} = {}
): void {
    const s = range?.s;
    const e = range?.e;

    useEffect(() => {
        const media = ref.current;
        if (!media || s === undefined || e === undefined) {
            return;
        }
        const r = {s, e};
        let frame: number | undefined;

        const toStart = () => {
            media.currentTime = r.s;
        };
        const checkEnd = () => {
            if (media.currentTime < r.e) {
                return;
            }
            if (loop) {
                toStart();
            } else {
                media.pause();
            }
        };
        const watch = () => {
            checkEnd();
            frame = media.paused ? undefined : requestAnimationFrame(watch);
        };
        const stopWatching = () => {
            if (frame !== undefined) {
                cancelAnimationFrame(frame);
                frame = undefined;
            }
        };

        const onLoadedMetadata = () => {
            if (!isInRange(media.currentTime, r)) {
                toStart();
            }
        };
        const onPlay = () => {
            if (!isInRange(media.currentTime, r)) {
                toStart();
            }
            if (typeof requestAnimationFrame === 'function') {
                stopWatching();
                frame = requestAnimationFrame(watch);
            }
        };
        const onSeeking = () => {
            if (!isInRange(media.currentTime, r)) {
                toStart();
            }
        };

        if (media.readyState >= 1) {
            onLoadedMetadata();
        }
        media.addEventListener('loadedmetadata', onLoadedMetadata);
        media.addEventListener('play', onPlay);
        media.addEventListener('seeking', onSeeking);
        media.addEventListener('timeupdate', checkEnd);
        media.addEventListener('pause', stopWatching);

        return () => {
            stopWatching();
            media.removeEventListener('loadedmetadata', onLoadedMetadata);
            media.removeEventListener('play', onPlay);
            media.removeEventListener('seeking', onSeeking);
            media.removeEventListener('timeupdate', checkEnd);
            media.removeEventListener('pause', stopWatching);
        };
    }, [ref, s, e, loop]);
}
