import {describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import {StopExposeSyncDialog} from './StopExposeSyncDialog';

// Used by the Radix checkbox, missing from jsdom
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;

function renderDialog() {
    const onConfirm = vi.fn(() => Promise.resolve());
    const onOpenChange = vi.fn();
    act(() => {
        render(
            <I18nextProvider i18n={createI18n('en')}>
                <StopExposeSyncDialog
                    open
                    onOpenChange={onOpenChange}
                    name="Press kit"
                    onConfirm={onConfirm}
                />
            </I18nextProvider>
        );
    });

    return {onConfirm, onOpenChange};
}

describe('StopExposeSyncDialog', () => {
    it('keeps the publication by default', async () => {
        const {onConfirm, onOpenChange} = renderDialog();

        expect(
            screen.getByText('Stop the synchronization of "Press kit"?')
        ).toBeTruthy();
        act(() => {
            fireEvent.click(screen.getByRole('button', {name: 'Stop'}));
        });

        await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false));
        expect(onConfirm).toHaveBeenCalledExactlyOnceWith(false);
    });

    it('also deletes the publication when asked', async () => {
        const {onConfirm} = renderDialog();

        act(() => {
            fireEvent.click(screen.getByTestId('expose-delete-publication'));
        });
        act(() => {
            fireEvent.click(screen.getByRole('button', {name: 'Stop'}));
        });

        await waitFor(() =>
            expect(onConfirm).toHaveBeenCalledExactlyOnceWith(true)
        );
    });
});
