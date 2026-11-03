import {useCallback} from 'react';
import {useQueryClient} from '@tanstack/react-query';
import type {Basket} from '@/types/api';
import {useBasketStore} from './basketStore';
import {invalidateBasket} from './basketQueries';

/**
 * Adds assets to the current basket and invalidates the cached view of the
 * basket they went into, taken from the response: the API picks the default
 * basket when there is no current one.
 */
export function useAddToCurrentBasket(): (
    assetIds: string[]
) => Promise<Basket> {
    const queryClient = useQueryClient();
    const addToCurrent = useBasketStore(s => s.addToCurrent);

    return useCallback(
        async (assetIds: string[]) => {
            const basket = await addToCurrent(assetIds);
            invalidateBasket(queryClient, basket.id);

            return basket;
        },
        [addToCurrent, queryClient]
    );
}
