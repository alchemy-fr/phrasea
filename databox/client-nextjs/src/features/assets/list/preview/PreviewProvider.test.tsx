import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import type {Asset} from '@/types/api';
import {usePreferencesStore} from '@/features/preferences/store';
import {PreviewProvider, usePreview} from './PreviewProvider';

vi.mock('@/lib/api/http', () => ({
    api: {put: vi.fn(() => Promise.resolve()), get: vi.fn()},
}));
vi.mock('@/features/assets/player/FilePlayer', () => ({
    FilePlayer: ({controls}: {controls?: boolean}) => (
        <div data-testid="player" data-controls={controls ? '1' : '0'} />
    ),
}));
vi.mock('@/features/attributes/AttributeList', () => ({
    AttributeList: () => <div data-testid="attributes" />,
}));

const asset = (id: string) =>
    ({
        id,
        name: `Asset ${id}`,
        preview: {file: {id: `f-${id}`, url: 'http://x/f', type: 'image/png'}},
        capabilities: {},
    }) as unknown as Asset;

function Chip({a}: {a: Asset}) {
    const preview = usePreview();

    return (
        <button
            data-testid={`chip-${a.id}`}
            onMouseEnter={e => preview.onEnter(a, e.currentTarget)}
            onMouseLeave={() => preview.onLeave(a)}
            onClick={e => preview.onLock(a, e.currentTarget)}
        />
    );
}

function renderChips() {
    const i18n = createI18n('en');

    return render(
        <I18nextProvider i18n={i18n}>
            <PreviewProvider>
                <Chip a={asset('1')} />
                <Chip a={asset('2')} />
            </PreviewProvider>
        </I18nextProvider>
    );
}

describe('PreviewProvider', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        usePreferencesStore.setState({preferences: {}, authenticated: true});
    });
    afterEach(() => vi.useRealTimers());

    it('opens on hover and closes on leave', () => {
        renderChips();
        fireEvent.mouseEnter(screen.getByTestId('chip-1'));
        act(() => vi.advanceTimersByTime(60));
        expect(screen.getByText('Asset 1')).toBeTruthy();
        expect(screen.getByTestId('player').dataset.controls).toBe('0');
        expect(screen.queryByLabelText('Unlock preview')).toBeNull();

        fireEvent.mouseLeave(screen.getByTestId('chip-1'));
        act(() => vi.advanceTimersByTime(150));
        expect(screen.queryByText('Asset 1')).toBeNull();
    });

    it('locks the preview on click until unlocked', () => {
        renderChips();
        fireEvent.click(screen.getByTestId('chip-1'));
        // Opened right away, interactive, with the unlock button
        expect(screen.getByText('Asset 1')).toBeTruthy();
        expect(screen.getByTestId('player').dataset.controls).toBe('1');
        expect(
            usePreferencesStore.getState().preferences.display?.previewLocked
        ).toBe(true);

        // Leaving the chip does not close a locked preview
        fireEvent.mouseLeave(screen.getByTestId('chip-1'));
        act(() => vi.advanceTimersByTime(150));
        expect(screen.getByText('Asset 1')).toBeTruthy();

        // Hovering another chip keeps the locked one displayed
        fireEvent.mouseEnter(screen.getByTestId('chip-2'));
        act(() => vi.advanceTimersByTime(60));
        expect(screen.getByText('Asset 1')).toBeTruthy();
        expect(screen.queryByText('Asset 2')).toBeNull();

        // Clicking another chip switches the locked preview to it
        fireEvent.click(screen.getByTestId('chip-2'));
        expect(screen.getByText('Asset 2')).toBeTruthy();
        expect(screen.getByTestId('player').dataset.controls).toBe('1');

        // The red lock closes and unlocks it
        fireEvent.click(screen.getByLabelText('Unlock preview'));
        expect(screen.queryByText('Asset 2')).toBeNull();
        expect(
            usePreferencesStore.getState().preferences.display?.previewLocked
        ).toBe(false);

        // Back to the hover behaviour
        fireEvent.mouseEnter(screen.getByTestId('chip-1'));
        act(() => vi.advanceTimersByTime(60));
        expect(screen.getByTestId('player').dataset.controls).toBe('0');
    });

    it('still opens on hover when the lock preference was persisted', () => {
        usePreferencesStore.setState({
            preferences: {display: {previewLocked: true} as any},
            authenticated: true,
        });
        renderChips();
        fireEvent.mouseEnter(screen.getByTestId('chip-1'));
        act(() => vi.advanceTimersByTime(60));
        expect(screen.getByText('Asset 1')).toBeTruthy();
        expect(screen.getByTestId('player').dataset.controls).toBe('1');
        expect(screen.getByLabelText('Unlock preview')).toBeTruthy();
    });
});
