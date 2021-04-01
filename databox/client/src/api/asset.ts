import apiClient from "./api-client";
import {Asset, Collection} from "../types";
import {ApiCollectionResponse, getHydraCollection} from "./hydra";

interface AssetOptions {
    url?: string;
    query?: string;
    workspaces?: string[];
    parents?: string[];
}

export async function getAssets(options: AssetOptions): Promise<ApiCollectionResponse<Asset>> {
    const res = options.url ? await apiClient.get(options.url) : await apiClient.get('/assets', {
        params: options,
    });

    return getHydraCollection<Asset>(res.data);
}

export async function getAsset(id: string): Promise<Asset> {
    const res = await apiClient.get(`/assets/${id}`);

    return res.data;
}

export async function patchAsset(id: string, data: Partial<any>): Promise<Asset> {
    const res = await apiClient.patch(`/assets/${id}`, data, {
        headers: {
            'Content-Type': 'application/merge-patch+json',
        },
    });

    return res.data;
}

type AssetPostType = {
    title: string;
    privacy: number;
    collection?: string,
    workspace?: string;
}

export async function postAsset(data: AssetPostType): Promise<Asset> {
    const res = await apiClient.post(`/assets`, data);

    return res.data;
}
