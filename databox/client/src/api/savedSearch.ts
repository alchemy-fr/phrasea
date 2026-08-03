import {apiClient} from '../init.ts';
import {SavedSearch, SavedSearchData} from '../types';
import {NormalizedCollectionResponse, getHydraCollection} from '@alchemy/api';
import {TSearchContext} from '../components/Media/Search/SearchContext.tsx';
import {PaginationParams} from '@alchemy/phrasea-framework';
import {EntityName} from './types.ts';

export type GetSavedSearchOptions = {
    query?: string;
    page?: number;
} & PaginationParams;

export async function getSavedSearches(
    params: GetSavedSearchOptions = {}
): Promise<NormalizedCollectionResponse<SavedSearch>> {
    const res = await apiClient.get(
        params.nextUrl ?? `/${EntityName.SavedSearch}`,
        {
            params,
        }
    );

    return getHydraCollection(res.data);
}

function normalizeSavedSearch(
    data: Partial<SavedSearch>
): Partial<SavedSearch> {
    if (data.privacy !== undefined) {
        // Radio inputs yield string values while the API expects an integer
        return {...data, privacy: Number(data.privacy)};
    }

    return data;
}

export async function putSavedSearch(
    id: string,
    data: Partial<SavedSearch>
): Promise<SavedSearch> {
    const res = await apiClient.put(
        `/${EntityName.SavedSearch}/${id}`,
        normalizeSavedSearch(data)
    );

    return res.data;
}

export async function postSavedSearch(
    data: Partial<SavedSearch>
): Promise<SavedSearch> {
    const res = await apiClient.post(
        `/${EntityName.SavedSearch}`,
        normalizeSavedSearch(data)
    );

    return res.data;
}

export async function getSavedSearch(id: string): Promise<SavedSearch> {
    return (await apiClient.get(`/${EntityName.SavedSearch}/${id}`)).data;
}

export async function deleteSavedSearch(id: string): Promise<void> {
    await apiClient.delete(`/${EntityName.SavedSearch}/${id}`);
}

export function getSearchData(searchContext: TSearchContext): SavedSearchData {
    return {
        query: searchContext.query,
        conditions: searchContext.conditions,
        sortBy: searchContext.sortBy,
    };
}

export function extractSearchData(
    searchData: SavedSearchData
): Pick<TSearchContext, 'query' | 'conditions' | 'sortBy'> {
    return {
        query: searchData.query || '',
        conditions: searchData.conditions,
        sortBy: searchData.sortBy,
    };
}
