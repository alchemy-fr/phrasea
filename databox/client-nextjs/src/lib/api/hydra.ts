import type {HydraCollection, Page} from '@/types/api';

export function toPage<T, E extends object = object>(
    response: HydraCollection<T, E>
): Page<T> {
    return {
        total: response['hydra:totalItems'],
        items: response['hydra:member'],
        next: response['hydra:view']?.['hydra:next'],
        previous: response['hydra:view']?.['hydra:previous'],
    };
}
