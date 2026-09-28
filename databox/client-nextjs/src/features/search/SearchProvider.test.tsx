import {beforeEach, describe, expect, it, vi} from 'vitest';
import {useEffect} from 'react';
import {act, render} from '@testing-library/react';
import {SearchProvider, SearchContextValue, useSearch} from './SearchProvider';

const nav = vi.hoisted(() => ({
    pathname: '/assets',
    search: '',
    push: vi.fn(),
}));

vi.mock('next/navigation', () => ({
    usePathname: () => nav.pathname,
    useSearchParams: () => new URLSearchParams(nav.search),
    useRouter: () => ({push: nav.push}),
}));

const probe = {} as {current: SearchContextValue};

function Probe() {
    const search = useSearch();
    useEffect(() => {
        probe.current = search;
    });

    return null;
}

function renderAt(pathname: string, search = '') {
    nav.pathname = pathname;
    nav.search = search;

    return render(
        <SearchProvider>
            <Probe />
        </SearchProvider>
    );
}

describe('SearchProvider', () => {
    beforeEach(() => {
        nav.push.mockReset();
    });

    it('keeps the last search while a dialog route is open', () => {
        const {rerender} = renderAt('/assets', 'q=cat');
        const checksum = probe.current.checksum;
        expect(probe.current.query).toBe('cat');

        // Opening a dialog: its URL carries no search
        nav.pathname = '/workspaces/1/manage/info';
        nav.search = '';
        rerender(
            <SearchProvider>
                <Probe />
            </SearchProvider>
        );
        expect(probe.current.query).toBe('cat');
        expect(probe.current.checksum).toBe(checksum);

        // Closing it: back to the very same search
        nav.pathname = '/assets';
        nav.search = 'q=cat';
        rerender(
            <SearchProvider>
                <Probe />
            </SearchProvider>
        );
        expect(probe.current.checksum).toBe(checksum);

        // The search screen URL remains the source of truth
        nav.search = 'q=dog';
        rerender(
            <SearchProvider>
                <Probe />
            </SearchProvider>
        );
        expect(probe.current.query).toBe('dog');
    });

    it('goes to the search screen when the search changes from elsewhere', () => {
        renderAt('/assets/42/_');
        act(() => probe.current.setQuery('cat'));
        expect(nav.push).toHaveBeenCalledWith('/assets?q=cat', {
            scroll: false,
        });
    });
});
