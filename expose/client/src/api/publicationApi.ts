import {Publication, UnauthorizedPublication} from '../types.ts';
import {apiClient} from '../init.ts';
import {RawAxiosRequestHeaders} from 'axios';
import {getPasswords} from '../lib/password.ts';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';
import {clearApiCache} from '@alchemy/phrasea-framework';

const publicationEntity = `publications`;

export async function loadPublication(
    id: string
): Promise<Publication | UnauthorizedPublication> {
    return (
        await apiClient.get(`/${publicationEntity}/${id}`, {
            headers: getPasswordHeaders(),
        })
    ).data;
}

export async function getPublications(
    options: Record<string, any> = {}
): Promise<NormalizedCollectionResponse<Publication>> {
    const res = await apiClient.get(`${publicationEntity}`, {
        params: options,
    });

    return getHydraCollection(res.data);
}

export async function putPublication(
    id: string,
    data: Partial<Publication>
): Promise<Publication> {
    return (await apiClient.put(`/${publicationEntity}/${id}`, data)).data;
}

export async function deletePublication(id: string): Promise<void> {
    await apiClient.delete(`/${publicationEntity}/${id}`);
    clearApiCache();
}

export function getPasswordHeaders(): RawAxiosRequestHeaders {
    const headers: RawAxiosRequestHeaders = {};

    const passwords = getPasswords();
    if (passwords) {
        headers['X-Passwords'] = passwords;
    }

    return headers;
}
