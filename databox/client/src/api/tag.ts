import {apiClient} from '../init.ts';
import {Tag} from '../types';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';

export const tagNS = '/tags';

type TagOptions = {
    query?: string;
    workspace?: string;
};

export async function getTags(
    options: TagOptions
): Promise<NormalizedCollectionResponse<Tag>> {
    const res = await apiClient.get(tagNS, {
        params: {
            ...options,
        },
    });

    return getHydraCollection<Tag>(res.data);
}

export async function getTag(id: string): Promise<Tag> {
    const res = await apiClient.get(`${tagNS}/${id}`);

    return res.data;
}

export async function postTag(data: Partial<Tag>): Promise<Tag> {
    const res = await apiClient.post(tagNS, data);

    return res.data;
}

export async function putTag(id: string, data: Partial<Tag>): Promise<Tag> {
    const res = await apiClient.put(`${tagNS}/${id}`, data);

    return res.data;
}

export async function deleteTag(id: string): Promise<void> {
    await apiClient.delete(`${tagNS}/${id}`);
}
