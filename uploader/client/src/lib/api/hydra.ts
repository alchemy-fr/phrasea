import type {HydraCollection, Page} from '@/types/api';

type LegacyHydraCollection<T> = {
    'hydra:totalItems'?: number;
    'hydra:member'?: T[];
    'hydra:view'?: {'hydra:next'?: string; 'hydra:previous'?: string};
};

/** A Hydra collection, with or without the `hydra:` prefix on its keys */
export function toPage<T, E extends object = object>(
    response: HydraCollection<T, E>
): Page<T> {
    const legacy = response as unknown as LegacyHydraCollection<T>;
    const items = response.member ?? legacy['hydra:member'] ?? [];

    return {
        total:
            response.totalItems ?? legacy['hydra:totalItems'] ?? items.length,
        items,
        next: response.view?.next ?? legacy['hydra:view']?.['hydra:next'],
        previous:
            response.view?.previous ?? legacy['hydra:view']?.['hydra:previous'],
    };
}

/**
 * Items of every page, following `next` from the first one. Only for lists
 * the caller needs as a whole: past `maxPages`, the rest is left out.
 */
export async function fetchAllPages<T>(
    first: Page<T> | Promise<Page<T>>,
    fetchNext: (url: string) => Promise<Page<T>>,
    maxPages = 20
): Promise<T[]> {
    let page = await first;
    const items = [...page.items];
    for (let i = 1; i < maxPages && page.next; i++) {
        page = await fetchNext(page.next);
        items.push(...page.items);
    }

    return items;
}
