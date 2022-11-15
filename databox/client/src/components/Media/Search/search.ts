import {Filters, OrderBy} from "./Filter";

export function queryToHash(
    query: string,
    filters: Filters,
    orderBy: OrderBy[],
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
    if (orderBy && orderBy.length > 0) {
        hash = `${hash ? '&' : ''}o=${encodeURIComponent(JSON.stringify(orderBy))}`;
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
    orderBy: OrderBy[];
    workspaceId: string | undefined;
    collectionId: string | undefined;
} {
    const params = new URLSearchParams(hash.substring(1));

    return {
        query: decodeURIComponent(params.get('q') || ''),
        filters: params.get('f') ? JSON.parse(decodeURIComponent(params.get('f') as string)) : [],
        collectionId: decodeURIComponent(params.get('c') || '') || undefined,
        workspaceId: decodeURIComponent(params.get('w') || '') || undefined,
        orderBy: params.get('o') ? JSON.parse(decodeURIComponent(params.get('o') as string)) : [],
    }
}
