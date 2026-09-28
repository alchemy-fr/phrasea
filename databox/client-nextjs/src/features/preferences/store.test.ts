import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';

const put = vi.fn(() => Promise.resolve());
vi.mock('@/lib/api/http', () => ({api: {put, get: vi.fn()}}));

const {usePreferencesStore, defaultDisplayPreferences} =
    await import('./store');

const update = (thumbSize: number) =>
    usePreferencesStore
        .getState()
        .updatePreference(
            'display',
            {...defaultDisplayPreferences, thumbSize},
            {throttle: 1000}
        );

describe('updatePreference throttle', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        put.mockClear();
        usePreferencesStore.setState({preferences: {}, authenticated: true});
    });
    afterEach(() => vi.useRealTimers());

    it('saves the first and the last value of a burst', async () => {
        await update(100);
        await update(110);
        await update(120);
        expect(
            usePreferencesStore.getState().preferences.display?.thumbSize
        ).toBe(120);
        expect(put).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(1000);
        expect(put).toHaveBeenCalledTimes(2);
        expect(put).toHaveBeenLastCalledWith(
            '/preferences',
            {
                name: 'display',
                value: expect.objectContaining({thumbSize: 120}),
                reset: undefined,
            },
            undefined
        );

        await vi.advanceTimersByTimeAsync(1000);
        expect(put).toHaveBeenCalledTimes(2);
    });
});

describe('updatePreference saves', () => {
    beforeEach(() => {
        put.mockClear();
        usePreferencesStore.setState({preferences: {}, authenticated: true});
    });

    it('sends the saves of a preference one at a time, the latest value last', async () => {
        const pending: (() => void)[] = [];
        put.mockImplementation(
            () => new Promise<void>(resolve => pending.push(resolve))
        );
        const toggle = (patch: Partial<typeof defaultDisplayPreferences>) =>
            usePreferencesStore
                .getState()
                .updatePreference('display', prev => ({
                    ...defaultDisplayPreferences,
                    ...(prev ?? {}),
                    ...patch,
                }));

        const saves = [
            toggle({layout: 'list'}),
            toggle({playVideos: true}),
            toggle({playOnHover: true}),
        ];
        // Only the first save is in flight
        expect(put).toHaveBeenCalledTimes(1);

        pending.shift()!();
        await vi.waitFor(() => expect(put).toHaveBeenCalledTimes(2));
        // The queued saves are merged into one, with the latest value
        expect(put).toHaveBeenLastCalledWith(
            '/preferences',
            {
                name: 'display',
                value: expect.objectContaining({
                    layout: 'list',
                    playVideos: true,
                    playOnHover: true,
                }),
                reset: undefined,
            },
            undefined
        );
        pending.shift()!();
        await Promise.all(saves);
        expect(put).toHaveBeenCalledTimes(2);
        put.mockImplementation(() => Promise.resolve());
    });

    it('sends the queued save right away when the page is left', async () => {
        const pending: (() => void)[] = [];
        put.mockImplementation(
            () => new Promise<void>(resolve => pending.push(resolve))
        );
        const update = usePreferencesStore.getState().updatePreference;
        void update('display', {...defaultDisplayPreferences, layout: 'list'});
        void update('display', {
            ...defaultDisplayPreferences,
            layout: 'list',
            playOnHover: true,
        });
        expect(put).toHaveBeenCalledTimes(1);

        window.dispatchEvent(new Event('pagehide'));
        await vi.waitFor(() => expect(put).toHaveBeenCalledTimes(2));
        expect(put).toHaveBeenLastCalledWith(
            '/preferences',
            {
                name: 'display',
                value: expect.objectContaining({playOnHover: true}),
                reset: undefined,
            },
            {keepalive: true}
        );

        // Nothing is left to send once the first save is done
        pending.forEach(resolve => resolve());
        await vi.waitFor(() => expect(put).toHaveBeenCalledTimes(2));
        put.mockImplementation(() => Promise.resolve());
    });
});
