import {describe, expect, it, vi} from 'vitest';
import {render} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {createI18n} from '@/i18n';
import type {Asset} from '@/types/api';
import {AssetList} from './AssetList';
import {SelectionProvider} from './SelectionProvider';

vi.mock('next/navigation', () => ({
    usePathname: () => '/assets',
    useSearchParams: () => new URLSearchParams(),
    useRouter: () => ({push: vi.fn(), replace: vi.fn()}),
}));

// jsdom does not implement scrolling
Element.prototype.scrollTo ??= () => undefined;

const pages = [
    [
        {
            id: 'asset-1',
            name: 'Asset 1',
            tags: [],
            capabilities: {},
            workspace: {id: 'ws'},
        } as unknown as Asset,
    ],
];

function renderList(loading: boolean) {
    return render(
        <I18nextProvider i18n={createI18n('en')}>
            <SelectionProvider>
                <AssetList
                    pages={pages}
                    loading={loading}
                    layout="grid"
                    thumbSize={200}
                />
            </SelectionProvider>
        </I18nextProvider>
    );
}

describe('AssetList while a new search runs', () => {
    it('keeps the previous results and covers them with the loader', () => {
        const {container, getByRole} = renderList(true);
        const overlay = container.querySelector(
            '[data-testid=asset-list-loading]'
        );
        const list = container.querySelector('[data-testid=asset-list]')!;

        expect(list.querySelector('[data-asset-id="asset-1"]')).not.toBeNull();
        expect(overlay).not.toBeNull();
        // Outside the scroll container, so it does not scroll away
        expect(overlay!.parentElement).toBe(list.parentElement);
        expect(getByRole('status').getAttribute('aria-label')).toBe(
            'Loading assets…'
        );
    });

    it('shows nothing over the results once loaded', () => {
        const {container} = renderList(false);

        expect(
            container.querySelector('[data-testid=asset-list-loading]')
        ).toBeNull();
    });
});
