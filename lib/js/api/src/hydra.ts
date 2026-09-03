import {HydraCollectionResponse, NormalizedCollectionResponse} from './types';

export function getHydraCollection<T, E extends {} = {}>(
    response: HydraCollectionResponse<T, E>
): NormalizedCollectionResponse<T, {}> {
    // Tolerate both API Platform 4 (un-prefixed) and legacy "hydra:"-prefixed keys
    const legacy = response as unknown as Record<string, any>;
    const res: NormalizedCollectionResponse<T, {}> = {
        total: response.totalItems ?? legacy['hydra:totalItems'],
        result: response.member ?? legacy['hydra:member'],
    };

    const view = response.view ?? legacy['hydra:view'];
    if (view) {
        res.first = view.first ?? view['hydra:first'];
        res.previous = view.previous ?? view['hydra:previous'];
        res.next = view.next ?? view['hydra:next'];
        res.last = view.last ?? view['hydra:last'];
    }

    return res;
}

export function normalizeNestedObjects<T extends Record<string, any>>(
    data: T,
    options: {
        ignoredKeys?: string[];
    } = {}
): T {
    const d: T = {} as T;

    Object.keys(data).forEach((k: keyof T) => {
        const v = data[k];

        if (
            v &&
            typeof v === 'object' &&
            Object.prototype.hasOwnProperty.call(v, '@id') &&
            (!options.ignoredKeys || !options.ignoredKeys.includes(k as string))
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

export function extractIdFromIri<T extends string | null | undefined>(
    iri: T
): T {
    if (!iri) {
        return iri;
    }

    const parts = iri.split('/');

    return parts[parts.length - 1] as T;
}

export function createIriFromId(entity: string, id: string): string {
    return `/${entity}/${id}`;
}

export function isEntityIri(entity: string, iri: string): boolean {
    return iri.startsWith(`/${entity}/`);
}
