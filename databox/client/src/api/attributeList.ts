import apiClient from './api-client';
import {AttributeList, AttributeListItem} from '../types';
import {ApiCollectionResponse, getHydraCollection} from './hydra';

const entityType = 'attribute-lists';

export type GetAttributeListOptions = {
    query?: string;
    page?: number;
};

export async function getAttributeLists(
    nextUrl?: string | undefined,
    params: GetAttributeListOptions = {}
): Promise<ApiCollectionResponse<AttributeList>> {
    const res = await apiClient.get(nextUrl ?? `/${entityType}`, {
        params,
    });

    return getHydraCollection(res.data);
}

export async function putAttributeList(
    id: string,
    data: Partial<AttributeList>
): Promise<AttributeList> {
    const res = await apiClient.put(
        `/${entityType}/${id}`,
        data
    );

    return res.data;
}

export async function sortAttributeList(
    id: string,
    data: string[]
): Promise<AttributeList> {
    const res = await apiClient.post(
        `/${entityType}/${id}/sort`,
        data
    );

    return res.data;
}

export async function postAttributeList(data: Partial<AttributeList>): Promise<AttributeList> {
    const res = await apiClient.post(`/${entityType}`, data);

    return res.data;
}

export async function getAttributeList(id: string): Promise<AttributeList> {
    return (await apiClient.get(`/attribute-lists/${id}`)).data;
}

export async function deleteAttributeList(id: string): Promise<void> {
    await apiClient.delete(`/attribute-lists/${id}`);
}

type AddToAttributeListInput = {
    items: AttributeListItem[];
};

export async function addToAttributeList(
    listId: string | undefined,
    data: AddToAttributeListInput
): Promise<AttributeList> {
    return (
        await apiClient.post(`/attribute-lists/${listId ?? 'default'}/items`, data)
    ).data;
}

export async function removeFromAttributeList(
    listId: string,
    itemIds: string[]
): Promise<AttributeList> {
    return (
        await apiClient.post(`/attribute-lists/${listId}/remove`, {
            items: itemIds,
        })
    ).data;
}
