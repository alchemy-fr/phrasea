import {beforeEach, describe, expect, it, vi} from 'vitest';
import type {PropsWithChildren, ReactElement} from 'react';
import {act, renderHook, waitFor} from '@testing-library/react';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import type {AttributeEntity} from '@/types/api';
import {AttributeEntityStatus} from '@/types/api';
import {
    type EntityGridFilters,
    type EntityGridSort,
    useEntityGridQuery,
} from './useEntityGridQuery';

const api = vi.hoisted(() => ({getAttributeEntities: vi.fn()}));
vi.mock('@/lib/api/metadata', () => ({
    getAttributeEntities: api.getAttributeEntities,
}));

const entity = (id: string, extra: Partial<AttributeEntity> = {}) =>
    ({
        id,
        value: id,
        translations: {},
        createdAt: '',
        updatedAt: '',
        status: AttributeEntityStatus.Approved,
        ...extra,
    }) as AttributeEntity;

// Typed against the app's @types/react: the workspace holds a second copy,
// the one @tanstack/react-query resolves
const Provider = QueryClientProvider as unknown as (
    props: PropsWithChildren<{client: QueryClient}>
) => ReactElement;

function wrapper({children}: PropsWithChildren) {
    return <Provider client={client}>{children}</Provider>;
}

let client: QueryClient;

const sort: EntityGridSort = {id: 'value', desc: false};
const filters: EntityGridFilters = {search: '', status: 'all'};

function renderGrid(
    options: {sort?: EntityGridSort; filters?: EntityGridFilters} = {}
) {
    return renderHook(
        () =>
            useEntityGridQuery({
                listId: 'l1',
                sort: options.sort ?? sort,
                filters: options.filters ?? filters,
            }),
        {wrapper}
    );
}

describe('useEntityGridQuery', () => {
    beforeEach(() => {
        client = new QueryClient({defaultOptions: {queries: {retry: false}}});
        api.getAttributeEntities.mockReset();
    });

    it('loads the next page and concatenates it', async () => {
        api.getAttributeEntities
            .mockResolvedValueOnce({
                total: 3,
                items: [entity('a'), entity('b')],
                next: '/next',
            })
            .mockResolvedValueOnce({total: 3, items: [entity('c')]});
        const {result} = renderGrid();

        await waitFor(() => expect(result.current.items).toHaveLength(2));
        expect(result.current.hasNextPage).toBe(true);
        await act(() => result.current.fetchNextPage());

        await waitFor(() =>
            expect(result.current.items.map(e => e.id)).toEqual(['a', 'b', 'c'])
        );
        expect(api.getAttributeEntities).toHaveBeenLastCalledWith(
            expect.objectContaining({url: '/next'})
        );
        expect(result.current.hasNextPage).toBe(false);
    });

    it('sends the filters and the sort', async () => {
        api.getAttributeEntities.mockResolvedValue({total: 0, items: []});
        renderGrid({
            sort: {id: 'status', desc: true},
            filters: {search: 'abc', status: 'pending', value: 'ignored'},
        });

        await waitFor(() =>
            expect(api.getAttributeEntities).toHaveBeenCalled()
        );
        expect(api.getAttributeEntities).toHaveBeenCalledWith({
            list: 'l1',
            value: 'abc',
            note: undefined,
            status: [AttributeEntityStatus.Pending],
            usage: true,
            limit: 100,
            order: {status: 'desc'},
            url: undefined,
        });
    });

    it('patches, inserts and removes entities in the cache', async () => {
        api.getAttributeEntities.mockResolvedValue({
            total: 2,
            items: [
                entity('a', {usage: {attributes: 3, humanAttributes: 1}}),
                entity('b'),
            ],
        });
        const {result} = renderGrid();
        await waitFor(() => expect(result.current.items).toHaveLength(2));

        act(() =>
            result.current.patchEntity('a', {
                status: AttributeEntityStatus.Pending,
            })
        );
        await waitFor(() =>
            expect(result.current.items[0].status).toBe(
                AttributeEntityStatus.Pending
            )
        );

        act(() => result.current.replaceEntity(entity('a', {value: 'A'})));
        await waitFor(() => expect(result.current.items[0].value).toBe('A'));
        expect(result.current.items[0].usage?.attributes).toBe(3);

        act(() => result.current.insertEntity(entity('n')));
        await waitFor(() =>
            expect(result.current.items.map(e => e.id)).toEqual(['n', 'a', 'b'])
        );
        expect(result.current.total).toBe(3);

        act(() => result.current.removeEntities(['a', 'n']));
        await waitFor(() =>
            expect(result.current.items.map(e => e.id)).toEqual(['b'])
        );
        expect(result.current.total).toBe(1);
    });
});
