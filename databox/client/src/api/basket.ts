import apiClient from './api-client';
import {Basket, BasketAsset} from '../types';
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

export async function getBasketAssets(id: string, params: GetBasketOptions = {}): Promise<ApiCollectionResponse<BasketAsset>> {
    const res = await apiClient.get(`/baskets/${id}/assets`, {
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

export type BasketAssetInput = {
    id: string;
}

type AddToBasketInput = {
    assets: BasketAssetInput[];
}

export async function addToBasket(basketId: string | undefined, data: AddToBasketInput): Promise<Basket> {
    return (await apiClient.post(`/baskets/${basketId ?? 'default'}/assets`, data)).data;
}
