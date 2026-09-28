import {describe, expect, it, vi} from 'vitest';
import {act, fireEvent, render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import type {Asset} from '@/types/api';
import {
    ShareDownloadDialog,
    type ShareDownloadItem,
} from './ShareDownloadDialog';

const misc = vi.hoisted(() => ({
    saveUrlAs: vi.fn((_url: string, _filename: string) => Promise.resolve()),
}));
vi.mock('@/lib/utils/misc', async importOriginal => ({
    ...(await importOriginal<object>()),
    ...misc,
}));

// Used by the Radix checkbox, missing from jsdom
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;

function asset(id: string): Asset {
    return {id, name: `Photo ${id}.jpg`} as unknown as Asset;
}

function renderDialog(items: ShareDownloadItem[]) {
    const resolve = vi.fn();
    act(() => {
        render(
            <I18nextProvider i18n={createI18n('en')}>
                <ShareDownloadDialog
                    open
                    onOpenChange={() => undefined}
                    resolve={resolve}
                    items={items}
                />
            </I18nextProvider>
        );
    });

    return {resolve};
}

describe('ShareDownloadDialog', () => {
    it('lists and downloads renditions pointing to the same file', async () => {
        misc.saveUrlAs.mockClear();
        const a = asset('a1');
        const {resolve} = renderDialog([
            {
                asset: a,
                renditions: [
                    {
                        id: 'r1',
                        name: 'preview',
                        label: 'Preview',
                        url: 'https://api/s/1/r/d1',
                        type: 'image/jpeg',
                    },
                    {
                        id: 'r2',
                        name: 'original',
                        label: 'Original',
                        url: 'https://api/s/1/r/d1',
                        type: 'image/jpeg',
                    },
                ],
            },
        ]);

        expect(screen.getAllByTestId('share-download-rendition')).toHaveLength(
            2
        );
        act(() => {
            fireEvent.click(screen.getByRole('checkbox', {name: 'Select all'}));
        });
        act(() => {
            screen.getByRole('button', {name: 'Download'}).click();
        });

        await waitFor(() => expect(resolve).toHaveBeenCalledWith(2));
        expect(misc.saveUrlAs.mock.calls.map(c => c[1])).toEqual([
            'Photo a1 - Preview.jpg',
            'Photo a1 - Original.jpg',
        ]);
    });

    it('downloads a rendition name for every asset having it', async () => {
        misc.saveUrlAs.mockClear();
        const {resolve} = renderDialog([
            {
                asset: asset('a1'),
                renditions: [
                    {
                        id: 'r1',
                        name: 'web',
                        label: 'Web',
                        url: 'u1',
                        type: 'image/jpeg',
                    },
                    {id: 'r2', name: 'original', label: 'Original', url: 'u2'},
                ],
            },
            {
                asset: asset('a2'),
                renditions: [
                    {
                        id: 'r3',
                        name: 'web',
                        label: 'Web',
                        url: 'u3',
                        type: 'image/jpeg',
                    },
                ],
            },
        ]);

        expect(screen.getAllByTestId('share-download-rendition')).toHaveLength(
            2
        );
        expect(screen.getByText('2 file(s)')).toBeTruthy();
        act(() => {
            fireEvent.click(screen.getByRole('checkbox', {name: /Web/}));
        });
        act(() => {
            screen.getByRole('button', {name: 'Download'}).click();
        });

        await waitFor(() => expect(resolve).toHaveBeenCalledWith(2));
        expect(misc.saveUrlAs.mock.calls.map(c => c[0])).toEqual(['u1', 'u3']);
    });
});
