import {FilterEntry, Filters, SortBy} from './Filter';
import {
    FacetType,
    NormalizedBucketKeyValue,
    ResolvedBucketValue,
} from '../Asset/Facets';
import {AttributeType} from '../../../api/attributes';

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

function encodeFilter(filter: FilterEntry): string {
    return [
        filter.a,
        filter.w,
        encode(filter.t),
        encode(JSON.stringify(filter.v.map(normalizeBucketValue))),
        filter.x === AttributeType.Text ? '' : filter.x,
        filter.i ? '1' : '',
    ].join(specSep);
}

function decodeFilter(str: string): FilterEntry {
    const [a, w, t, v, x, i] = str.split(specSep);

    return {
        a,
        x: x as AttributeType | undefined,
        w: (w as FacetType) || undefined,
        t: decode(t),
        v: JSON.parse(decode(v)).map(
            denormalizeBucketValue
        ) as ResolvedBucketValue[],
        i: i ? 1 : undefined,
    };
}

function normalizeBucketValue(
    v: ResolvedBucketValue
): NormalizedBucketKeyValue {
    if (typeof v === 'object') {
        return {
            v: v.value,
            l: v.label,
        };
    }

    return v;
}

function denormalizeBucketValue(
    v: NormalizedBucketKeyValue
): ResolvedBucketValue {
    if (typeof v === 'object') {
        return {
            value: v.v,
            label: v.l,
        };
    }

    return v;
}

export function queryToHash(
    query: string,
    filters: Filters,
    sortBy: SortBy[],
    geolocation: string | undefined
): string {
    let hash = '';
    if (query) {
        hash += `q=${encodeURIComponent(query)}`;
    }
    if (filters && filters.length > 0) {
        const uriComponent = filters.map(encodeFilter).join(arraySep);
        hash += `${hash ? '&' : ''}f=${encodeURIComponent(uriComponent)}`;
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
    filters: Filters;
    sortBy: SortBy[];
    geolocation: string | undefined;
} {
    const params = new URLSearchParams(hash.substring(1));

    return {
        query: decodeURIComponent(params.get('q') || ''),
        filters: params.get('f')
            ? (params.get('f') as string).split(arraySep).map(decodeFilter)
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
