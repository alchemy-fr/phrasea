import apiClient from './api-client';
import {SavedSearch, SavedSearchData} from '../types';
import {ApiCollectionResponse, getHydraCollection} from './hydra';
import {TSearchContext} from '../components/Media/Search/SearchContext.tsx';

const entityType = 'saved-searches';

export type GetSavedSearchOptions = {
    query?: string;
    page?: number;
};

export async function getSavedSearches(
    nextUrl?: string | undefined,
    params: GetSavedSearchOptions = {}
): Promise<ApiCollectionResponse<SavedSearch>> {
    const res = await apiClient.get(nextUrl ?? `/${entityType}`, {
        params,
    });

    return getHydraCollection(res.data);
}

export async function putSavedSearch(
    id: string,
    data: Partial<SavedSearch>
): Promise<SavedSearch> {
    const res = await apiClient.put(`/${entityType}/${id}`, data);

    return res.data;
}

export async function postSavedSearch(
    data: Partial<SavedSearch>
): Promise<SavedSearch> {
    const res = await apiClient.post(`/${entityType}`, data);

    return res.data;
}

export async function getSavedSearch(id: string): Promise<SavedSearch> {
    return (await apiClient.get(`/${entityType}/${id}`)).data;
}

export async function deleteSavedSearch(id: string): Promise<void> {
    await apiClient.delete(`/${entityType}/${id}`);
}

export function getSearchData(searchContext: TSearchContext): SavedSearchData {
    return {
        query: searchContext.query,
        conditions: searchContext.conditions,
        sortBy: searchContext.sortBy,
        geolocationEnabled: !!searchContext.geolocation,
    };
}
