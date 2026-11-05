import {afterEach, describe, expect, it, vi} from 'vitest';
import {renderHook} from '@testing-library/react';
import type {RefObject} from 'react';
import {useClipPlayback} from './useClipPlayback';

/**
 * Media element double: jsdom implements neither the playback nor the time.
 */
class FakeMedia extends EventTarget {
    currentTime = 0;
    paused = true;
    readyState = 0;
    pause = vi.fn(() => {
        this.paused = true;
        this.dispatchEvent(new Event('pause'));
    });

    play() {
        this.paused = false;
        this.dispatchEvent(new Event('play'));
    }

    seek(time: number) {
        this.currentTime = time;
        this.dispatchEvent(new Event('seeking'));
    }

    playTo(time: number) {
        this.currentTime = time;
        this.dispatchEvent(new Event('timeupdate'));
    }
}

type Range = {s: number; e: number};

function setup(
    range: Range | Range[] | undefined,
    options?: {loop?: boolean},
    media = new FakeMedia()
) {
    const ref = {current: media} as unknown as RefObject<HTMLMediaElement>;
    const toRanges = (r: Range | Range[] | undefined) =>
        r === undefined ? undefined : Array.isArray(r) ? r : [r];
    const hook = renderHook(
        ({r}: {r: Range | Range[] | undefined}) =>
            useClipPlayback(ref, toRanges(r), options),
        {
            initialProps: {r: range},
        }
    );

    return {media, ...hook};
}

describe('useClipPlayback', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('starts at the beginning of the range', () => {
        const {media} = setup({s: 12, e: 30});
        expect(media.currentTime).toBe(0);

        media.readyState = 1;
        media.dispatchEvent(new Event('loadedmetadata'));
        expect(media.currentTime).toBe(12);
    });

    it('applies to a media already loaded', () => {
        const media = new FakeMedia();
        media.readyState = 4;
        media.currentTime = 40;
        setup({s: 12, e: 30}, undefined, media);

        expect(media.currentTime).toBe(12);
    });

    it('brings the playback back into the range', () => {
        const {media} = setup({s: 12, e: 30});

        media.currentTime = 35;
        media.play();
        expect(media.currentTime).toBe(12);

        media.seek(5);
        expect(media.currentTime).toBe(12);

        media.seek(20);
        expect(media.currentTime).toBe(20);
    });

    it('stops at the end of the range', () => {
        const {media} = setup({s: 12, e: 30});
        media.play();

        media.playTo(29.9);
        expect(media.pause).not.toHaveBeenCalled();

        media.playTo(30.1);
        expect(media.pause).toHaveBeenCalledTimes(1);
        expect(media.paused).toBe(true);

        // Played again: from the start of the range
        media.play();
        expect(media.currentTime).toBe(12);
    });

    it('loops within the range', () => {
        const {media} = setup({s: 12, e: 30}, {loop: true});
        media.play();

        media.playTo(30);
        expect(media.pause).not.toHaveBeenCalled();
        expect(media.currentTime).toBe(12);
    });

    it('watches the end on every frame while playing', () => {
        const frames: FrameRequestCallback[] = [];
        vi.stubGlobal('requestAnimationFrame', (cb: FrameRequestCallback) => {
            frames.push(cb);

            return frames.length;
        });
        vi.stubGlobal('cancelAnimationFrame', vi.fn());

        const {media} = setup({s: 12, e: 30});
        media.play();
        expect(frames).toHaveLength(1);

        // No timeupdate event: the frame callback catches the end
        media.currentTime = 30.02;
        frames[0](0);
        expect(media.pause).toHaveBeenCalledTimes(1);
        // Not scheduled any more once paused
        expect(frames).toHaveLength(1);
    });

    it('plays the ranges one after the other', () => {
        const ranges = [
            {s: 2, e: 4},
            {s: 10, e: 12},
            {s: 20, e: 25},
        ];
        const {media} = setup(ranges);
        media.readyState = 1;
        media.dispatchEvent(new Event('loadedmetadata'));
        expect(media.currentTime).toBe(2);

        media.play();
        media.playTo(3.9);
        expect(media.currentTime).toBe(3.9);
        // From the end of a range to the start of the next one
        media.playTo(4.05);
        expect(media.currentTime).toBe(10);
        media.playTo(12);
        expect(media.currentTime).toBe(20);
        expect(media.pause).not.toHaveBeenCalled();

        // Stops after the last one
        media.playTo(25.1);
        expect(media.pause).toHaveBeenCalledTimes(1);

        // Played again: from the first one
        media.play();
        expect(media.currentTime).toBe(2);
    });

    it('seeks to the next range from a gap', () => {
        const {media} = setup([
            {s: 2, e: 4},
            {s: 10, e: 12},
        ]);

        media.seek(6);
        expect(media.currentTime).toBe(10);
        media.seek(11);
        expect(media.currentTime).toBe(11);
        media.seek(0.5);
        expect(media.currentTime).toBe(2);
        // After the last one: the first one
        media.seek(30);
        expect(media.currentTime).toBe(2);
    });

    it('loops back to the first range', () => {
        const {media} = setup(
            [
                {s: 2, e: 4},
                {s: 10, e: 12},
            ],
            {loop: true}
        );
        media.play();

        media.playTo(4);
        expect(media.currentTime).toBe(10);
        media.playTo(12);
        expect(media.currentTime).toBe(2);
        expect(media.pause).not.toHaveBeenCalled();
    });

    it('ignores a new array of the same ranges', () => {
        const {media, rerender} = setup([{s: 2, e: 4}]);
        media.readyState = 1;
        // Not through an event: only a new effect would move it back
        media.currentTime = 50;
        rerender({r: [{s: 2, e: 4}]});
        expect(media.currentTime).toBe(50);
        rerender({r: [{s: 3, e: 4}]});
        expect(media.currentTime).toBe(3);
    });

    it('does nothing without a range, and stops listening', () => {
        const {media, rerender} = setup(undefined);
        media.currentTime = 50;
        media.play();
        media.playTo(60);
        expect(media.currentTime).toBe(60);
        expect(media.pause).not.toHaveBeenCalled();

        rerender({r: {s: 1, e: 2}});
        media.readyState = 1;
        media.dispatchEvent(new Event('loadedmetadata'));
        expect(media.currentTime).toBe(1);

        rerender({r: undefined});
        media.playTo(10);
        expect(media.pause).not.toHaveBeenCalled();
    });
});
