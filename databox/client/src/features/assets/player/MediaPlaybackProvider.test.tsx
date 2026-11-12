import {afterEach, describe, expect, it, vi} from 'vitest';
import {act, cleanup, render, renderHook} from '@testing-library/react';
import {type ReactNode, useEffect} from 'react';
import {
    type MediaPlaybackControl,
    MediaPlaybackProvider,
    useMediaPlayback,
    useRegisterMediaPlayback,
} from './MediaPlaybackProvider';

/** A player whose time is set by the test */
function fakeControl(time = 0) {
    const listeners = new Set<() => void>();
    const control = {
        time,
        playFrom: vi.fn((t: number) => {
            control.time = t;
            listeners.forEach(l => l());
        }),
        currentTime: () => control.time,
        subscribe: (listener: () => void) => {
            listeners.add(listener);

            return () => listeners.delete(listener);
        },
        emit: (t: number) => {
            control.time = t;
            listeners.forEach(l => l());
        },
        listeners,
    };

    return control;
}

type Ctx = ReturnType<typeof useMediaPlayback>;

function Player({control}: {control?: MediaPlaybackControl}) {
    useRegisterMediaPlayback(control);

    return null;
}

function renderWithPlayers(players: (MediaPlaybackControl | undefined)[]) {
    const seen: {ctx?: Ctx} = {};
    function Consumer() {
        const ctx = useMediaPlayback();
        useEffect(() => {
            seen.ctx = ctx;
        });

        return null;
    }
    const ui = (list: (MediaPlaybackControl | undefined)[]): ReactNode => (
        <MediaPlaybackProvider>
            {list.map((c, i) => (
                <Player key={i} control={c} />
            ))}
            <Consumer />
        </MediaPlaybackProvider>
    );
    const result = render(ui(players));

    return {
        seen,
        rerender: (list: (MediaPlaybackControl | undefined)[]) =>
            result.rerender(ui(list)),
    };
}

describe('MediaPlaybackProvider', () => {
    afterEach(cleanup);

    it('is undefined outside of a provider', () => {
        const {result} = renderHook(() => useMediaPlayback());

        expect(result.current).toBeUndefined();
    });

    it('has no player until one registers, and none once it leaves', () => {
        const control = fakeControl(3);
        const {seen, rerender} = renderWithPlayers([]);

        expect(seen.ctx?.available).toBe(false);
        expect(seen.ctx?.currentTime()).toBeUndefined();
        seen.ctx?.playFrom(5);

        rerender([control]);
        expect(seen.ctx?.available).toBe(true);
        expect(seen.ctx?.currentTime()).toBe(3);

        rerender([undefined]);
        expect(seen.ctx?.available).toBe(false);
        expect(seen.ctx?.currentTime()).toBeUndefined();
    });

    it('relays the playback to the player', () => {
        const control = fakeControl();
        const {seen} = renderWithPlayers([control]);

        act(() => seen.ctx?.playFrom(12));

        expect(control.playFrom).toHaveBeenCalledWith(12);
        expect(seen.ctx?.currentTime()).toBe(12);
    });

    it('keeps the last player registered; another one leaving changes nothing', () => {
        const first = fakeControl(1);
        const second = fakeControl(2);
        const {seen, rerender} = renderWithPlayers([first]);

        rerender([first, second]);
        expect(seen.ctx?.currentTime()).toBe(2);

        rerender([undefined, second]);
        expect(seen.ctx?.available).toBe(true);
        expect(seen.ctx?.currentTime()).toBe(2);
    });

    it('notifies the subscribers of the time of the player, a new one too', () => {
        const first = fakeControl(1);
        const second = fakeControl(5);
        const {seen, rerender} = renderWithPlayers([first]);
        const listener = vi.fn();
        const unsubscribe = seen.ctx!.subscribeTime(listener);

        first.emit(2);
        expect(listener).toHaveBeenCalledTimes(1);

        rerender([undefined, second]);
        listener.mockClear();
        second.emit(6);
        expect(listener).toHaveBeenCalled();
        expect(first.listeners.size).toBe(0);
        expect(seen.ctx?.currentTime()).toBe(6);

        unsubscribe();
        listener.mockClear();
        second.emit(7);
        expect(listener).not.toHaveBeenCalled();
        expect(second.listeners.size).toBe(0);
    });
});
