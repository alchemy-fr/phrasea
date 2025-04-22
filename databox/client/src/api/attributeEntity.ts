import apiClient from './api-client';
import {AttributeEntity} from '../types';
import {ApiCollectionResponse, getHydraCollection} from './hydra';

const attributeEntityNS = '/attribute-entities';

type AttributeEntityOptions = {
    query?: string;
    type?: string;
    workspace?: string;
};

export async function getAttributeEntities(
    options: AttributeEntityOptions
): Promise<ApiCollectionResponse<AttributeEntity>> {
    const res = await apiClient.get(attributeEntityNS, {
        params: {
            ...options,
        },
    });

    return getHydraCollection<AttributeEntity>(res.data);
}

export async function postAttributeEntity(
    workspaceId: string,
    data: Partial<AttributeEntity>
): Promise<AttributeEntity> {
    const res = await apiClient.post(attributeEntityNS, {
        ...data,
        workspace: `/workspaces/${workspaceId}`,
    });

    return res.data;
}

export async function putAttributeEntity(
    id: string,
    data: Partial<AttributeEntity>
): Promise<AttributeEntity> {
    const res = await apiClient.put(`${attributeEntityNS}/${id}`, data);

    return res.data;
}

export async function deleteAttributeEntity(id: string): Promise<void> {
    await apiClient.delete(`${attributeEntityNS}/${id}`);
}
