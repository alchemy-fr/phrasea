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
