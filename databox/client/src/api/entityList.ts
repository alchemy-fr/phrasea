import apiClient from './api-client';
import {EntityList} from '../types';
import {ApiCollectionResponse, getHydraCollection} from './hydra';

const entityTypeNS = '/entity-lists';

type EntityListOptions = {
    query?: string;
    workspace?: string;
};

export async function getEntityLists(
    options: EntityListOptions
): Promise<ApiCollectionResponse<EntityList>> {
    const res = await apiClient.get(entityTypeNS, {
        params: {
            ...options,
            [`order[value]`]: 'asc',
        },
    });

    return getHydraCollection<EntityList>(res.data);
}

export async function postEntityList(
    workspaceId: string,
    data: Partial<EntityList>
): Promise<EntityList> {
    const res = await apiClient.post(entityTypeNS, {
        ...data,
        workspace: `/workspaces/${workspaceId}`,
    });

    return res.data;
}

export async function putEntityList(
    id: string,
    data: Partial<EntityList>
): Promise<EntityList> {
    const res = await apiClient.put(`${entityTypeNS}/${id}`, data);

    return res.data;
}

export async function deleteEntityList(id: string): Promise<void> {
    await apiClient.delete(`${entityTypeNS}/${id}`);
}
