import {beforeEach, describe, expect, it, vi} from 'vitest';
import type {ReactNode} from 'react';
import {act, renderHook} from '@testing-library/react';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import type {Basket} from '@/types/api';

const addToBasket = vi.hoisted(() => vi.fn());
vi.mock('@/lib/api/misc', () => ({addToBasket}));
vi.mock('@/lib/analytics/events', async importOriginal => ({
    ...(await importOriginal<typeof import('@/lib/analytics/events')>()),
    track: vi.fn(),
}));

const {useAddToCurrentBasket} = await import('./useAddToCurrentBasket');
const {useBasketStore} = await import('./basketStore');

const basket = (id: string, assetCount: number) =>
    ({
        id,
        name: id,
        assetCount,
        capabilities: {edit: true},
    }) as unknown as Basket;

function setup() {
    const queryClient = new QueryClient();
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries');
    const wrapper = ({children}: {children: ReactNode}) => (
        <QueryClientProvider client={queryClient}>
            {children}
        </QueryClientProvider>
    );
    const {result} = renderHook(() => useAddToCurrentBasket(), {wrapper});

    return {result, invalidate};
}

beforeEach(() => {
    vi.clearAllMocks();
    useBasketStore.setState({baskets: [], current: undefined});
});

describe('useAddToCurrentBasket', () => {
    it('adds to the current basket and invalidates its queries', async () => {
        const before = basket('b', 1);
        useBasketStore.setState({baskets: [before], current: before});
        addToBasket.mockResolvedValueOnce(basket('b', 3));
        const {result, invalidate} = setup();

        await act(() => result.current(['a1', 'a2']));

        expect(addToBasket).toHaveBeenCalledWith('b', ['a1', 'a2']);
        expect(useBasketStore.getState().baskets[0].assetCount).toBe(3);
        expect(useBasketStore.getState().current?.assetCount).toBe(3);
        expect(invalidate).toHaveBeenCalledWith({queryKey: ['basket', 'b']});
        expect(invalidate).toHaveBeenCalledWith({
            queryKey: ['basket-assets', 'b'],
        });
    });

    it('invalidates the basket the API picked when there is no current one', async () => {
        addToBasket.mockResolvedValueOnce(basket('new', 1));
        const {result, invalidate} = setup();

        await act(() => result.current(['a1']));

        expect(addToBasket).toHaveBeenCalledWith(undefined, ['a1']);
        expect(useBasketStore.getState().current?.id).toBe('new');
        expect(invalidate).toHaveBeenCalledWith({queryKey: ['basket', 'new']});
        expect(invalidate).toHaveBeenCalledWith({
            queryKey: ['basket-assets', 'new'],
        });
    });
});
