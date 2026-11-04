import {describe, expect, it, vi} from 'vitest';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {Tooltip as TooltipPrimitive} from 'radix-ui';
import {createI18n} from '@/i18n';
import {TicketDialog} from './TicketDialog';

const screenshot = vi.hoisted(() => ({
    isScreenCaptureSupported: () => true,
    captureScreenshot: vi.fn<() => Promise<string>>(),
    imageFileToDataUrl: vi.fn(),
}));
vi.mock('./screenshot', () => screenshot);
vi.mock('@/lib/auth/AuthProvider', () => ({
    useAuth: () => ({user: {id: 'u1', username: 'jdoe'}}),
}));

function isHidden(slot: string): boolean {
    return document
        .querySelector(`[data-slot="${slot}"]`)!
        .classList.contains('invisible');
}

describe('TicketDialog', () => {
    it('hides itself while the page is captured', async () => {
        let hiddenOnCapture: boolean[] = [];
        screenshot.captureScreenshot.mockImplementation(async () => {
            hiddenOnCapture = [
                isHidden('dialog-content'),
                isHidden('dialog-overlay'),
            ];

            return 'data:image/png;base64,AAAA';
        });

        render(
            <I18nextProvider i18n={createI18n('en')}>
                <TooltipPrimitive.Provider>
                    <TicketDialog
                        open
                        onOpenChange={() => {}}
                        page={{
                            url: 'https://databox/assets',
                            path: '/assets',
                            capturedAt: '2026-10-15T00:00:00Z',
                        }}
                    />
                </TooltipPrimitive.Provider>
            </I18nextProvider>
        );
        fireEvent.click(
            screen.getByRole('button', {name: 'Capture this page'})
        );

        await waitFor(() => screen.getByRole('img', {name: 'Screenshot'}));
        expect(hiddenOnCapture).toEqual([true, true]);
        expect(isHidden('dialog-content')).toBe(false);
        expect(isHidden('dialog-overlay')).toBe(false);
    });
});
