import apiClient from './api-client';
import {EntityList} from '../types';
import {NormalizedCollectionResponse, getHydraCollection} from '@alchemy/api';
import {SortWay} from './common.ts';

export const entityTypeNS = '/entity-lists';

type EntityListOptions = {
    query?: string;
    workspace?: string;
};

export async function getEntityLists(
    workspaceId: string,
    options?: EntityListOptions
): Promise<NormalizedCollectionResponse<EntityList>> {
    const res = await apiClient.get(entityTypeNS, {
        params: {
            ...(options ?? {}),
            workspace: workspaceId,
            [`order[value]`]: SortWay.ASC,
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
