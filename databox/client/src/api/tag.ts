import {apiClient} from '../init.ts';
import {Tag} from '../types';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';
import {EntityName, QueryAndPaginationParams} from './types.ts';

type TagOptions = {
    workspace?: string;
} & QueryAndPaginationParams;

export async function getTags({
    nextUrl,
    ...options
}: TagOptions): Promise<NormalizedCollectionResponse<Tag>> {
    const res = await apiClient.get(nextUrl ?? EntityName.Tag, {
        params: {
            ...options,
        },
    });

    return getHydraCollection<Tag>(res.data);
}

export async function getTag(id: string): Promise<Tag> {
    const res = await apiClient.get(`${EntityName.Tag}/${id}`);

    return res.data;
}

export async function postTag(data: Partial<Tag>): Promise<Tag> {
    const res = await apiClient.post(EntityName.Tag, data);

    return res.data;
}

export async function putTag(id: string, data: Partial<Tag>): Promise<Tag> {
    const res = await apiClient.put(`${EntityName.Tag}/${id}`, data);

    return res.data;
}

export async function deleteTag(id: string): Promise<void> {
    await apiClient.delete(`${EntityName.Tag}/${id}`);
}
