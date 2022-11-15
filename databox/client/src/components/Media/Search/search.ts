import {Filters, SortBy} from "./Filter";

export function queryToHash(
    query: string,
    filters: Filters,
    sortBy: SortBy[],
    workspaceId: string | undefined,
    collectionId: string | undefined,
): string {
    let hash = '';
    if (query) {
        hash += `q=${encodeURIComponent(query)}`;
    }
    if (filters && filters.length > 0) {
        hash = `${hash ? '&' : ''}f=${encodeURIComponent(JSON.stringify(filters))}`;
    }
    if (sortBy && sortBy.length > 0) {
        hash = `${hash ? '&' : ''}s=${encodeURIComponent(JSON.stringify(sortBy))}`;
    }
    if (workspaceId) {
        hash += `${hash ? '&' : ''}w=${workspaceId}`;
    }
    if (collectionId) {
        hash += `${hash ? '&' : ''}c=${collectionId}`;
    }

    return hash;
}

export function hashToQuery(hash: string): {
    query: string;
    filters: Filters;
    sortBy: SortBy[];
    workspaceId: string | undefined;
    collectionId: string | undefined;
} {
    const params = new URLSearchParams(hash.substring(1));

    return {
        query: decodeURIComponent(params.get('q') || ''),
        filters: params.get('f') ? JSON.parse(decodeURIComponent(params.get('f') as string)) : [],
        collectionId: decodeURIComponent(params.get('c') || '') || undefined,
        workspaceId: decodeURIComponent(params.get('w') || '') || undefined,
        sortBy: params.get('s') ? JSON.parse(decodeURIComponent(params.get('s') as string)) : [],
    }
}
