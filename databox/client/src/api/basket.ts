import apiClient from './api-client';
import {Basket} from '../types';
import {ApiCollectionResponse, getHydraCollection} from './hydra';
import {clearAssociationIds} from "./clearAssociation.ts";

export type GetBasketOptions = {
    query?: string;
    page?: number;
}

export async function getBaskets(params: GetBasketOptions = {}): Promise<ApiCollectionResponse<Basket>> {
    const res = await apiClient.get('/baskets', {
        params,
    });

    return getHydraCollection(res.data);
}

export async function putBasket(
    id: string,
    data: Partial<Basket>
): Promise<Basket> {
    const res = await apiClient.put(
        `/baskets/${id}`,
        clearAssociationIds(data)
    );

    return res.data;
}

export async function postBasket(
    data: Partial<Basket>
): Promise<Basket> {
    const res = await apiClient.post(
        `/baskets`,
        data
    );

    return res.data;
}

export async function getBasket(id: string): Promise<Basket> {
    return (await apiClient.get(`/baskets/${id}`)).data;
}

export async function deleteBasket(id: string): Promise<void> {
    await apiClient.delete(`/baskets/${id}`);
}

export async function addToBasket(basketId: string, assets: string[]): Promise<Basket> {
    return await apiClient.post(`/baskets/${basketId}/assets`, {
        assets,
    });
}
