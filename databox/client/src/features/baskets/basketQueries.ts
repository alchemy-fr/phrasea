import type {QueryClient} from '@tanstack/react-query';

/**
 * Refetches what the basket view shows of a basket (the basket itself and
 * its items) after they changed; queries that are not mounted are refetched
 * on their next use.
 */
export function invalidateBasket(
    queryClient: QueryClient,
    basketId: string
): void {
    void queryClient.invalidateQueries({queryKey: ['basket', basketId]});
    void queryClient.invalidateQueries({queryKey: ['basket-assets', basketId]});
}
