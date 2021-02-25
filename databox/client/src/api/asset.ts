import apiClient from "./api-client";
import {Asset} from "../types";

interface AssetOptions {
    query?: string;
    workspaces?: string[];
    parents?: string[];
}

export async function getAssets(options: AssetOptions) {
    const res = await apiClient.get('/assets', {
        params: options,
    });

    return res.data;
}

export async function getAsset(id: string) {
    const res = await apiClient.get(`/assets/${id}`);

    return res.data;
}

export async function patchAsset(id: string, data: Partial<Asset>) {
    const res = await apiClient.patch(`/assets/${id}`, data);

    return res.data;
}
