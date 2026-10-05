import {describe, expect, it, vi} from 'vitest';
import {render, screen, waitFor} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import type {Asset} from '@/types/api';
import {AssetStatus} from '@/types/api';
import {MergeDuplicatesDialog} from './MergeDuplicatesDialog';

vi.mock('next/navigation', () => ({
    usePathname: () => '/assets',
    useSearchParams: () => new URLSearchParams(),
    useRouter: () => ({push: vi.fn(), replace: vi.fn()}),
}));

// jsdom implements neither of these, and Radix's dialog needs both
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;
Element.prototype.scrollIntoView ??= () => undefined;

function asset(id: string, thumbnailUrl: string, rejected: boolean): Asset {
    return {
        'id': id,
        '@id': `/assets/${id}`,
        'name': id,
        'status': rejected ? AssetStatus.Quarantined : AssetStatus.Accepted,
        'createdAt': '2026-01-01T10:00:00+00:00',
        'capabilities': {},
        'attributes': [],
        'tags': [],
        'collections': [],
        'workspace': {id: 'ws', name: 'Workspace'},
        'thumbnail': {
            file: {id: `${id}-thumb`, type: 'image/jpeg', url: thumbnailUrl},
        },
        'source': {
            id: `${id}-source`,
            type: 'image/png',
            accepted: !rejected,
        },
    } as unknown as Asset;
}

describe('MergeDuplicatesDialog', () => {
    it('shows the thumbnail rendition of the incoming (quarantined) file', async () => {
        const incoming = asset('incoming', 'https://cdn/incoming.jpg', true);
        const existing = asset('existing', 'https://cdn/existing.jpg', false);
        const i18n = createI18n('en');

        render(
            <I18nextProvider i18n={i18n}>
                <MergeDuplicatesDialog
                    open
                    onOpenChange={vi.fn()}
                    asset={incoming}
                    duplicates={[{asset: existing, analyzers: ['checksum']}]}
                />
            </I18nextProvider>
        );

        // The incoming file is rejected by definition: its analysis state must
        // not hide the rendition the user compares the duplicate against.
        await waitFor(() =>
            expect(
                screen
                    .getAllByRole('img')
                    .map(img => img.getAttribute('src'))
                    .sort()
            ).toEqual(['https://cdn/existing.jpg', 'https://cdn/incoming.jpg'])
        );
    });
});
