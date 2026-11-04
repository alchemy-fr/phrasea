import type {HydraCollection, Page} from '@/types/api';

export function toPage<T>(response: HydraCollection<T>): Page<T> {
    return {
        total: response.totalItems,
        items: response.member,
        next: response.view?.next,
        previous: response.view?.previous,
    };
}
