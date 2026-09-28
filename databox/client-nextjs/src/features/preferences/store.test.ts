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
        expect(put).toHaveBeenLastCalledWith('/preferences', {
            name: 'display',
            value: expect.objectContaining({thumbSize: 120}),
            reset: undefined,
        });

        await vi.advanceTimersByTimeAsync(1000);
        expect(put).toHaveBeenCalledTimes(2);
    });
});
