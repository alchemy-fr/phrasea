import apiClient from './api-client';
import {NormalizedCollectionResponse, getHydraCollection} from '@alchemy/api';
import {Attribute, Entity, Tag} from '../types';
import {AttributeBatchAction} from './types.ts';

export type AssetDataTemplate = {
    name: string;
    workspace: string;
    collection: string;
    includeCollectionChildren: boolean;
    attributes: Attribute[] | AttributeBatchAction[] | undefined;
    privacy?: number | undefined | null;
    public: boolean;
    tags?: Tag[] | undefined;
    title?: string | undefined;
} & Entity;

const assetDataTemplateNS = 'asset-data-templates';

export async function postAssetDataTemplate(
    data: Partial<AssetDataTemplate>
): Promise<void> {
    await apiClient.post(assetDataTemplateNS, data);
}

export async function putAssetDataTemplate(
    id: string,
    data: Partial<AssetDataTemplate>
): Promise<void> {
    await apiClient.put(`/${assetDataTemplateNS}/${id}`, data);
}

type GetAssetDataTemplatesOptions = {
    query?: string;
    workspace: string;
    collection?: string | undefined;
};

export async function getAssetDataTemplates(
    options?: GetAssetDataTemplatesOptions
): Promise<NormalizedCollectionResponse<AssetDataTemplate>> {
    const res = await apiClient.get(assetDataTemplateNS, {
        params: {
            ...(options ?? {}),
        },
    });

    return getHydraCollection(res.data);
}

export async function getAssetDataTemplate(
    id: string
): Promise<AssetDataTemplate> {
    const res = await apiClient.get(`/${assetDataTemplateNS}/${id}`);

    return res.data;
}
