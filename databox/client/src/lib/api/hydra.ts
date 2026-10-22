import type {HydraCollection, Page} from '@/types/api';

export function toPage<T, E extends object = object>(
    response: HydraCollection<T, E>
): Page<T> {
    return {
        total: response.totalItems,
        items: response.member,
        next: response.view?.next,
        previous: response.view?.previous,
    };
}

/**
 * Items of every page, following `next` from the first one. Only for lists
 * bounded by a filter, which the caller needs as a whole: past `maxPages`,
 * the rest is left out.
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
