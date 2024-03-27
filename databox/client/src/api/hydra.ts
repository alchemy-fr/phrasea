import {TFacets} from '../components/Media/Asset/Facets';
import {Asset} from '../types';

export type ApiCollectionResponse<T, E extends {} = {}> = {
    total: number;
    first: string | null;
    previous: string | null;
    next: string | null;
    last: string | null;
    result: T[];
    facets?: TFacets | undefined;
} & E;

type HydraCollectionResponse<T, E extends {} = {}> = {
    'hydra:totalItems': number;
    'hydra:view'?: {
        'hydra:first': string;
        'hydra:previous': string;
        'hydra:next': string;
        'hydra:last': string;
    };
    'hydra:member': T[];
} & E;

export interface ApiHydraObjectResponse {
    '@id': string;
    '@type': string;
}

export function getHydraCollection<T, E extends {} = {}>(
    response: HydraCollectionResponse<T, E>
): ApiCollectionResponse<T, {}> {
    const res: ApiCollectionResponse<T, {}> = {
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

export function getAssetsHydraCollection(
    response: HydraCollectionResponse<
        Asset,
        {
            facets: TFacets;
        }
    >
) {
    return {
        ...getHydraCollection(response),
        facets: response.facets,
    };
}
