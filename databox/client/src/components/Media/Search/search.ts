import {SortBy} from './Filter';
import {AQLQueries, AQLQuery} from "./AQL/query.ts";

const specSep = ';';
const arraySep = ',';

export enum BuiltInFilter {
    Collection = 'c',
    Workspace = 'w',
    CreatedAt = 'createdAt',
    Score = 'score',
}

function encode(str: string): string {
    return str.replace(/%/g, '%9').replace(/,/g, '%1').replace(/;/g, '%2');
}

function decode(str: string): string {
    return str.replace(/%1/g, ',').replace(/%2/g, ';').replace(/%9/g, '%');
}

function encodeSortBy(sortBy: SortBy): string {
    return [
        sortBy.a,
        sortBy.w.toString(),
        sortBy.g ? '1' : '',
        encode(sortBy.t),
    ].join(specSep);
}

function decodeSortBy(str: string): SortBy {
    const [a, w, g, t] = str.split(specSep);

    return {
        a,
        w: parseInt(w) as 0 | 1,
        t: decode(t),
        g: g === '1',
    };
}

export function queryToHash(
    query: string,
    conditions: AQLQueries,
    sortBy: SortBy[],
    geolocation: string | undefined
): string {
    let hash = '';
    if (query) {
        hash += `q=${encodeURIComponent(query)}`;
    }
    if (conditions && conditions.length > 0) {
        hash += `${hash ? '&' : ''}${conditions.filter(q => !q.disabled).map(q => `f=${q.id}:${encodeURIComponent(q.query)}`).join('&')}`;
    }
    if (sortBy && sortBy.length > 0) {
        hash += `${hash ? '&' : ''}s=${encodeURIComponent(
            sortBy.map(encodeSortBy).join(arraySep)
        )}`;
    }
    if (geolocation) {
        hash += `${hash ? '&' : ''}l=${encodeURIComponent(geolocation)}`;
    }

    return hash;
}

export function hashToQuery(hash: string): {
    query: string;
    conditions: AQLQuery[];
    sortBy: SortBy[];
    geolocation: string | undefined;
} {
    const params = new URLSearchParams(hash.substring(1));

    return {
        query: decodeURIComponent(params.get('q') || ''),
        conditions: params.has('f')
            ? (params.getAll('f'))
                .map(q => {
                    const [id, ...query] = q.split(':');

                    return {
                        query: query.join(':'),
                        id,
                    } as AQLQuery;
                })
            : [],
        sortBy: params.get('s')
            ? decodeURIComponent(params.get('s') as string)
                .split(arraySep)
                .map(decodeSortBy)
            : [],
        geolocation: params.get('l')
            ? decodeURIComponent(params.get('l') as string)
            : undefined,
    };
}
