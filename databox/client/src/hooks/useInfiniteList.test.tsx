import {describe, expect, it, vi} from 'vitest';
import {act, render, waitFor} from '@testing-library/react';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import type {Page} from '@/types/api';
import {type InfiniteList, useInfiniteList} from './useInfiniteList';

type Item = {id: string; name: string};

const item = (id: string): Item => ({id, name: `Item ${id}`});

function Harness({
    fetchPage,
    onRender,
}: {
    fetchPage: (a: {query: string; url?: string}) => Promise<Page<Item>>;
    onRender: (list: InfiniteList<Item>) => void;
}) {
    onRender(
        useInfiniteList<Item>({
            queryKey: ['items'],
            fetchPage,
            debounce: 20,
        })
    );

    return null;
}

function setup(fetchPage: (a: {query: string; url?: string}) => Page<Item>) {
    const queryClient = new QueryClient({
        defaultOptions: {queries: {retry: false}},
    });
    const fetcher = vi.fn(async (a: {query: string; url?: string}) =>
        fetchPage(a)
    );
    const result = {current: undefined as unknown as InfiniteList<Item>};
    render(
        <QueryClientProvider client={queryClient}>
            <Harness
                fetchPage={fetcher}
                onRender={list => (result.current = list)}
            />
        </QueryClientProvider>
    );

    return {result, fetcher, queryClient};
}

describe('useInfiniteList', () => {
    it('puts the debounced search in the query key', async () => {
        const {result, fetcher, queryClient} = setup(({query}) => ({
            total: 1,
            items: [item(query || 'all')],
        }));
        await waitFor(() => expect(result.current.items).toHaveLength(1));

        act(() => {
            result.current.setQuery('a');
            result.current.setQuery('ab ');
        });
        expect(result.current.searching).toBe(true);
        await waitFor(() =>
            expect(result.current.items.map(i => i.id)).toEqual(['ab'])
        );

        expect(fetcher.mock.calls.map(c => c[0].query)).toEqual(['', 'ab']);
        expect(
            queryClient
                .getQueryCache()
                .find({queryKey: ['items', {query: 'ab'}]})
        ).toBeTruthy();
        expect(result.current.searching).toBe(false);
        // A search never lists the whole list
        expect(result.current.complete).toBe(false);
    });

    it('lists an item once across pages, and follows next only', async () => {
        const {result} = setup(({url}) =>
            url
                ? // Shifted by an insertion: "2" again, and a short last page
                  {total: 4, items: [item('2'), item('3')]}
                : {total: 4, items: [item('1'), item('2')], next: '/p2'}
        );
        await waitFor(() => expect(result.current.hasMore).toBe(true));
        expect(result.current.complete).toBe(false);

        act(() => result.current.loadMore());

        await waitFor(() =>
            expect(result.current.items.map(i => i.id)).toEqual(['1', '2', '3'])
        );
        expect(result.current.hasMore).toBe(false);
        // Fewer items than the total, but no next page
        expect(result.current.complete).toBe(true);
    });

    it('loads the remaining pages on demand', async () => {
        const pages: Record<string, Page<Item>> = {
            '': {total: 3, items: [item('1')], next: '/p2'},
            '/p2': {total: 3, items: [item('2')], next: '/p3'},
            '/p3': {total: 3, items: [item('3')]},
        };
        const {result, fetcher} = setup(({url}) => pages[url ?? '']);
        await waitFor(() => expect(result.current.items).toHaveLength(1));
        expect(fetcher).toHaveBeenCalledTimes(1);

        await act(() => result.current.loadAll());

        await waitFor(() =>
            expect(result.current.items.map(i => i.id)).toEqual(['1', '2', '3'])
        );
        expect(fetcher.mock.calls.map(c => c[0].url)).toEqual([
            undefined,
            '/p2',
            '/p3',
        ]);
        expect(result.current.complete).toBe(true);
    });
});
