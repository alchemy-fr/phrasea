import {Filters} from "./Filter";

export function queryToHash(query: string, filters: Filters): string {
    return `q=${encodeURI(query)}&f=${encodeURI(JSON.stringify(filters))}`;
}

export function hashToQuery(hash: string): {
    query: string,
    filters: Filters,
} {
    const params = new URLSearchParams(hash.substring(1));

    return {
        query: params.get('q') || '',
        filters: params.get('f') ? JSON.parse(params.get('f') as string) : [],
    }
}
