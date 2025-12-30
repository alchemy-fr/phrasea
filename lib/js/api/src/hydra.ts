import {HydraCollectionResponse, NormalizedCollectionResponse} from './types';

export function getHydraCollection<T, E extends {} = {}>(
    response: HydraCollectionResponse<T, E>
): NormalizedCollectionResponse<T, {}> {
    const res: NormalizedCollectionResponse<T, {}> = {
        total: response['hydra:totalItems'],
        result: response['hydra:member'],
        first: null,
        previous: null,
        next: null,
        last: null,
    };

    const hydraView = response['hydra:view'];
    if (hydraView) {
        res.first = hydraView['hydra:first'];
        res.previous = hydraView['hydra:previous'];
        res.next = hydraView['hydra:next'];
        res.last = hydraView['hydra:last'];
    }

    return res;
}
