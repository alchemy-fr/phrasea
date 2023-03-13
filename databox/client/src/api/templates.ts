import apiClient from "./api-client";
import {AttributeBatchAction} from "./asset";
import {ApiCollectionResponse, getHydraCollection} from "./hydra";
import {Attribute, Tag} from "../types";

export type AssetDataTemplate = {
    id: string;
    name: string;
    workspace: string;
    attributes: Attribute[] | AttributeBatchAction[] | undefined;
    privacy?: number | undefined;
    tags?: Tag[] | string[] | undefined;
    title?: string | undefined;
}

const assetDataTemplateNS = 'asset-data-templates';

export async function postAssetDataTemplate(data: Partial<AssetDataTemplate>): Promise<void> {
    await apiClient.post(assetDataTemplateNS, data);
}

type GetAssetDataTemplatesOptions = {
    query?: string;
    workspace: string;
}

export async function getAssetDataTemplates(options?: GetAssetDataTemplatesOptions): Promise<ApiCollectionResponse<AssetDataTemplate>> {
    const res = await apiClient.get(assetDataTemplateNS, {
        params: {
            ...(options ?? {}),
        },
    });

    return getHydraCollection(res.data);
}

export async function getAssetDataTemplate(id: string): Promise<AssetDataTemplate> {
    const res = await apiClient.get(`/${assetDataTemplateNS}/${id}`);

    return res.data;
}
