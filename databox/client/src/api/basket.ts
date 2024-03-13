import apiClient from './api-client';
import {Basket} from '../types';
import {ApiCollectionResponse, getHydraCollection} from './hydra';

export async function getBaskets(): Promise<ApiCollectionResponse<Basket>> {
    const res = await apiClient.get('/baskets');

    return getHydraCollection(res.data);
}
