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

export function normalizeNestedObjects<T extends Record<string, any>>(
    data: T,
    options: {
        expectKeys?: string[]
    } = {}
): T {
    const d: T = {} as T;

    Object.keys(data).forEach((k: keyof T) => {
        const v = data[k];

        if (
            v &&
            typeof v === 'object' &&
            Object.prototype.hasOwnProperty.call(v, '@id')
            && (!options.expectKeys || !options.expectKeys.includes(k as string))
        ) {
            d[k] = v['@id'];
        } else if (Array.isArray(v)) {
            d[k] = normalizeNestedObjects(v, options);
        } else {
            d[k] = v;
        }
    });

    return d;
}
