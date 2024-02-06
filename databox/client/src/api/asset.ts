import apiClient from './api-client';
import {Asset, AssetFileVersion, Attribute} from '../types';
import {ApiCollectionResponse, getHydraCollection} from './hydra';
import {AxiosRequestConfig} from 'axios';

export interface GetAssetOptions {
    url?: string;
    query?: string;
    workspaces?: string[];
    parents?: string[];
    filters?: any;
    order: Record<string, 'asc' | 'desc'>;
    group?: string[] | undefined;
    context?:
        | {
              position?: string | undefined;
          }
        | undefined;
}

export type ESDebug = {
    query: object;
    esQueryTime: number;
    totalResponseTime: number;
};

export async function getAssets(
    options: GetAssetOptions,
    requestConfig?: AxiosRequestConfig
): Promise<
    ApiCollectionResponse<
        Asset,
        {
            debug: ESDebug;
        }
    >
> {
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
            totalResponseTime: res.config.meta!.responseTime!,
        },
    };
}

export type SearchSuggestion = {
    id: string;
    name: string;
    hl: string;
    t: string;
}

export async function getSearchSuggestions(
    query: string,
    requestConfig?: AxiosRequestConfig
): Promise<
    ApiCollectionResponse<
        SearchSuggestion,
        {
            debug: ESDebug;
        }
    >
> {
    const res = await apiClient.get('/assets/suggest', {
              params: {
                  query,
              },
              ...requestConfig,
          });

    return {
        ...getHydraCollection<SearchSuggestion>(res.data),
        debug: {
            query: res.data['debug:es'].query,
            esQueryTime: res.data['debug:es'].time,
            totalResponseTime: res.config.meta!.responseTime!,
        },
    };
}

export async function getAsset(id: string): Promise<Asset> {
    const res = await apiClient.get(`/assets/${id}`);

    return res.data;
}

export async function getAssetAttributes(
    assetId: string | string[]
): Promise<Attribute[]> {
    const res = await apiClient.get(`/attributes`, {
        params: {
            assetId,
        },
    });

    return res.data['hydra:member'];
}

export async function getAssetFileVersions(
    assetId: string | string[]
): Promise<ApiCollectionResponse<AssetFileVersion>> {
    const res = await apiClient.get(`/asset-file-versions`, {
        params: {
            assetId,
        },
    });

    return getHydraCollection(res.data);
}

export enum AttributeBatchActionEnum {
    Set = 'set',
    Replace = 'replace',
    Add = 'add',
    Delete = 'delete',
}

export type AttributeBatchAction = {
    action?: AttributeBatchActionEnum | undefined;
    id?: string | undefined;
    value?: any | undefined;
    definitionId?: string | undefined;
    locale?: string | undefined;
    position?: number | undefined;
};

export async function attributeBatchUpdate(
    assetId: string | string[],
    actions: AttributeBatchAction[]
): Promise<Asset> {
    actions = actions.map(a => {
        if (a.action === 'delete') {
            return a;
        }

        return {
            ...a,
            origin: 'human',
        };
    });

    if (typeof assetId === 'string') {
        return (
            await apiClient.post(`/assets/${assetId}/attributes`, {
                actions,
            })
        ).data;
    } else {
        return (
            await apiClient.post(`/attributes/batch-update`, {
                assets: assetId,
                actions,
            })
        ).data;
    }
}

export async function deleteAssetAttribute(id: string): Promise<void> {
    await apiClient.delete(`/attributes/${id}`);
}

export async function triggerAssetWorkflow(id: string): Promise<void> {
    await apiClient.put(`/assets/${id}/trigger-workflow`, {});
}

export async function deleteAsset(id: string): Promise<void> {
    await apiClient.delete(`/assets/${id}`);
}

export async function deleteAssets(ids: string[]): Promise<void> {
    await apiClient.delete(`/assets`, {
        data: {
            ids,
        },
    });
}

export async function putAsset(id: string, data: Partial<any>): Promise<Asset> {
    const res = await apiClient.put(`/assets/${id}`, data, {
        headers: {
            'Content-Type': 'application/merge-patch+json',
        },
    });

    return res.data;
}

export type AssetApiInput = {
    title?: string;
    privacy?: number;
    tags?: string[];
    collection?: string;
    workspace?: string;
    sourceFileId?: string;
    pendingUploadToken?: string;
    sequence?: number;
};

export type NewAssetPostType = {
    relationship?:
        | {
              source: string;
              type: string;
              sourceFile?: string | undefined;
              integration?: string | undefined;
          }
        | undefined;
    attributes?: AttributeBatchAction[] | undefined;
} & AssetApiInput;

export async function postAsset(data: NewAssetPostType): Promise<Asset> {
    const res = await apiClient.post(`/assets`, data);

    return res.data;
}

export async function postMultipleAssets(
    assets: NewAssetPostType[]
): Promise<Asset[]> {
    const res = await apiClient.post(`/assets/multiple`, {
        assets,
    });

    return res.data.assets;
}
