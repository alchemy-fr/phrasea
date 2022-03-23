import apiClient, {RequestConfig} from "./api-client";
import {Asset, Attribute, AttributeDefinition} from "../types";
import {ApiCollectionResponse, getHydraCollection} from "./hydra";
import {AxiosRequestConfig} from "axios";

interface AssetOptions {
    url?: string;
    query?: string;
    workspaces?: string[];
    parents?: string[];
}

export type ESDebug = {
    query: object;
    esQueryTime: number;
    totalResponseTime: number;
}


export async function getAssets(options: AssetOptions, requestConfig?: AxiosRequestConfig): Promise<ApiCollectionResponse<Asset, {
    debug: ESDebug;
}>> {
    const res = options.url
        ? await apiClient.get(options.url, requestConfig)
        : await apiClient.get('/assets', {
            params: options,
            ...requestConfig,
        });

    return {
        ...getHydraCollection<Asset>(res.data),
        debug: {
            query: res.data['debug:es'].query,
            esQueryTime: res.data['debug:es'].time,
            totalResponseTime: (res.config as RequestConfig).meta!.responseTime!,
        }
    };
}

export async function getAsset(id: string): Promise<Asset> {
    const res = await apiClient.get(`/assets/${id}`);

    return res.data;
}

export async function getAssetAttributes(assetId: string): Promise<Attribute[]> {
    const res = await apiClient.get(`/attributes`, {
        params: {
            assetId,
        }
    });

    return res.data['hydra:member'];
}

export async function putAssetAttribute(id: string | undefined, assetId: string, definitionId: string, value: any): Promise<void> {
    if (id) {
        await apiClient.put(`/attributes/${id}`, {
            value,
        });

        return;
    }

    await apiClient.post(`/attributes`, {
        origin: 'human',
        asset: `/assets/${assetId}`,
        definition: `/attribute-definitions/${definitionId}`,
        value,
    });
}

export async function getWorkspaceAttributeDefinitions(workspaceId: string): Promise<AttributeDefinition[]> {
    const res = await apiClient.get(`/attribute-definitions`, {
        params: {
            workspaceId,
        }
    });

    return res.data['hydra:member'];
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
