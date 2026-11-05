'use client';

import {type RefObject, useEffect} from 'react';
import type {ClipTimeRange} from './types';
import {clipPlaybackTarget} from './clip';

/**
 * Bounds the playback of a media element to time ranges (sorted, not
 * overlapping): it starts at the start of the first one once the metadata is
 * loaded, jumps from the end of a range to the start of the next one, and
 * stops (or loops back to the first one, with `loop`) at the end of the last
 * one. Played or sought out of the ranges, it goes to the start of the next
 * one (of the first one, after the last).
 *
 * `timeupdate` only fires a few times per second: the end of the ranges is
 * also watched on every frame while playing, so that nothing past them is
 * shown.
 */
export function useClipPlayback(
    ref: RefObject<HTMLMediaElement | null>,
    ranges: ClipTimeRange[] | undefined,
    {loop = false}: {loop?: boolean} = {}
): void {
    // The ranges by value: a new array of the same ranges changes nothing
    const key = ranges?.length ? JSON.stringify(ranges) : undefined;

    useEffect(() => {
        const media = ref.current;
        if (!media || key === undefined) {
            return;
        }
        const r: ClipTimeRange[] = JSON.parse(key);
        let frame: number | undefined;

        const moveIn = () => {
            const target = clipPlaybackTarget(media.currentTime, r);
            if (target) {
                media.currentTime = target.time;
            }
        };
        const checkEnd = () => {
            const target = clipPlaybackTarget(media.currentTime, r);
            if (!target) {
                return;
            }
            if (!target.ended || loop) {
                media.currentTime = target.time;
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

        const onPlay = () => {
            moveIn();
            if (typeof requestAnimationFrame === 'function') {
                stopWatching();
                frame = requestAnimationFrame(watch);
            }
        };

        if (media.readyState >= 1) {
            moveIn();
        }
        media.addEventListener('loadedmetadata', moveIn);
        media.addEventListener('play', onPlay);
        media.addEventListener('seeking', moveIn);
        media.addEventListener('timeupdate', checkEnd);
        media.addEventListener('pause', stopWatching);

        return () => {
            stopWatching();
            media.removeEventListener('loadedmetadata', moveIn);
            media.removeEventListener('play', onPlay);
            media.removeEventListener('seeking', moveIn);
            media.removeEventListener('timeupdate', checkEnd);
            media.removeEventListener('pause', stopWatching);
        };
    }, [ref, key, loop]);
}
