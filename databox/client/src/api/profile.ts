import {apiClient} from '../init.ts';
import {Profile, ProfileItem} from '../types';
import {NormalizedCollectionResponse, getHydraCollection} from '@alchemy/api';
import {PaginationParams} from './types.ts';

const entityType = 'profiles';

export type GetProfileOptions = {
    query?: string;
    page?: number;
} & PaginationParams;

export async function getProfiles({
    nextUrl,
    ...params
}: GetProfileOptions = {}): Promise<NormalizedCollectionResponse<Profile>> {
    const res = await apiClient.get(nextUrl ?? `/${entityType}`, {
        params,
    });

    return getHydraCollection(res.data);
}

export async function putProfile(
    id: string,
    data: Partial<Profile>
): Promise<Profile> {
    const res = await apiClient.put(`/${entityType}/${id}`, data);

    return res.data;
}

export async function putProfileItem(
    listId: string,
    itemId: string,
    data: Partial<ProfileItem>
): Promise<ProfileItem> {
    const res = await apiClient.put(
        `/${entityType}/${listId}/items/${itemId}`,
        data
    );

    return res.data;
}

export async function sortProfileItems(
    id: string,
    data: string[]
): Promise<void> {
    await apiClient.post(`/${entityType}/${id}/sort`, data);
}

export async function postProfile(data: Partial<Profile>): Promise<Profile> {
    const res = await apiClient.post(`/${entityType}`, data);

    return res.data;
}

export async function getProfile(id: string): Promise<Profile> {
    return (await apiClient.get(`/${entityType}/${id}`)).data;
}

export async function deleteProfile(id: string): Promise<void> {
    await apiClient.delete(`/${entityType}/${id}`);
}

type AddToProfileInput = {
    items: ProfileItem[];
};

export async function addToProfile(
    listId: string | undefined,
    data: AddToProfileInput
): Promise<Profile> {
    return (
        await apiClient.post(
            `/${entityType}/${listId ?? 'default'}/items`,
            data
        )
    ).data;
}

export async function removeFromProfile(
    listId: string,
    itemIds: string[]
): Promise<Profile> {
    return (
        await apiClient.post(`/${entityType}/${listId}/remove`, {
            items: itemIds,
        })
    ).data;
}
