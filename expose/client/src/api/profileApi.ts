import {PublicationProfile} from '../types.ts';
import {apiClient} from '../init.ts';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';

const profileEntity = `publication-profiles`;

export async function getProfiles(
    options: Record<string, any> = {}
): Promise<NormalizedCollectionResponse<PublicationProfile>> {
    const res = await apiClient.get(`${profileEntity}`, {
        params: options,
    });

    return getHydraCollection(res.data);
}

export async function putProfile(
    id: string,
    data: Partial<PublicationProfile>
): Promise<PublicationProfile> {
    return (await apiClient.put(`/${profileEntity}/${id}`, data)).data;
}
