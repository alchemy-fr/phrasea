import {PublicationProfile} from '../types.ts';
import {apiClient} from '../init.ts';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';
import {clearApiCache} from '@alchemy/phrasea-framework';

const profileEntity = `publication-profiles`;

export async function getProfiles(
    options: Record<string, any> = {}
): Promise<NormalizedCollectionResponse<PublicationProfile>> {
    const res = await apiClient.get(`${profileEntity}`, {
        params: options,
    });

    return getHydraCollection(res.data);
}

export async function getProfile(id: string): Promise<PublicationProfile> {
    return (await apiClient.get(`/${profileEntity}/${id}`)).data;
}

export async function putProfile(
    id: string,
    data: Partial<PublicationProfile>
): Promise<PublicationProfile> {
    return (await apiClient.put(`/${profileEntity}/${id}`, data)).data;
}

export async function postProfile(
    data: Partial<PublicationProfile>
): Promise<PublicationProfile> {
    return (await apiClient.post(`/${profileEntity}`, data)).data;
}

export async function deleteProfile(id: string): Promise<void> {
    await apiClient.delete(`/${profileEntity}/${id}`);
    clearApiCache();
}
