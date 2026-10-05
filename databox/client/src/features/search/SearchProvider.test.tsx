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

describe('SearchProvider.upsertCondition', () => {
    beforeEach(() => {
        nav.push.mockReset();
    });

    it('replaces the condition with the same id', () => {
        renderAt('/assets', 'f=%40isStory%3A%40isStory+IS+false');
        act(() =>
            probe.current.upsertCondition({
                id: '@isStory',
                query: '@isStory IS true',
            })
        );
        const url: string = nav.push.mock.calls[0][0];
        const params = new URLSearchParams(url.split('?')[1]);
        expect(params.getAll('f')).toEqual(['@isStory:@isStory IS true']);
    });

    it('clears the text query in the same navigation with resetQuery', () => {
        renderAt('/assets', 'q=cat');
        act(() =>
            probe.current.upsertCondition({
                id: '@isStory',
                query: '@isStory IS true',
                resetQuery: true,
            })
        );
        expect(nav.push).toHaveBeenCalledTimes(1);
        const url: string = nav.push.mock.calls[0][0];
        const params = new URLSearchParams(url.split('?')[1]);
        expect(params.get('q')).toBeNull();
        expect(params.getAll('f')).toEqual(['@isStory:@isStory IS true']);
        expect(probe.current.inputQuery.current).toBe('');
    });
});

describe('SearchProvider.selectCollection', () => {
    beforeEach(() => {
        nav.push.mockReset();
    });

    const pushedFilters = (call = 0) => {
        const url: string = nav.push.mock.calls[call][0];

        return new URLSearchParams(url.split('?')[1] ?? '').getAll('f');
    };

    const collection = (extra: object = {}) =>
        ({
            '@id': '/collections/c1',
            'id': 'c1',
            'name': 'Folder',
            ...extra,
        }) as any;

    it('filters by @collection for a regular collection', () => {
        renderAt('/assets');
        act(() => probe.current.selectCollection('c1', collection()));
        expect(pushedFilters()).toEqual(['@collection:@collection IS "c1"']);
    });

    it('filters by @story for a story collection', () => {
        renderAt('/assets');
        act(() =>
            probe.current.selectCollection(
                'c1',
                collection({
                    name: '',
                    storyAsset: {
                        '@id': '/assets/a1',
                        'id': 'a1',
                        'name': 'My story',
                    },
                })
            )
        );
        expect(pushedFilters()).toEqual(['@story:@story IS "a1"']);
    });

    it('replaces a story filter when selecting a collection', () => {
        renderAt('/assets');
        act(() =>
            probe.current.selectCollection(
                'c1',
                collection({name: '', storyAsset: {id: 'a1'}})
            )
        );
        expect(pushedFilters()).toEqual(['@story:@story IS "a1"']);
        act(() => probe.current.selectCollection('c2', collection({id: 'c2'})));
        expect(pushedFilters(1)).toEqual(['@collection:@collection IS "c2"']);
    });
});
