import {apiClient} from '../init.ts';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';
import {Attribute, Entity, Tag} from '../types';
import {AttributeBatchAction, EntityName} from './types.ts';

export type AssetDataTemplate = {
    name: string;
    workspace: string;
    collection: string;
    includeCollectionChildren: boolean;
    attributes: Attribute[] | AttributeBatchAction[] | undefined;
    privacy?: number | undefined | null;
    public: boolean;
    tags?: Tag[] | undefined;
    assetName?: string | undefined;
} & Entity;

export async function postAssetDataTemplate(
    data: Partial<AssetDataTemplate>
): Promise<void> {
    await apiClient.post(EntityName.AssetDataTemplate, data);
}

export async function putAssetDataTemplate(
    id: string,
    data: Partial<AssetDataTemplate>
): Promise<void> {
    await apiClient.put(`/${EntityName.AssetDataTemplate}/${id}`, data);
}

type GetAssetDataTemplatesOptions = {
    query?: string;
    workspace: string;
    collection?: string | undefined;
};

export async function getAssetDataTemplates(
    options?: GetAssetDataTemplatesOptions
): Promise<NormalizedCollectionResponse<AssetDataTemplate>> {
    const res = await apiClient.get(EntityName.AssetDataTemplate, {
        params: {
            ...(options ?? {}),
        },
    });

    return getHydraCollection(res.data);
}

export async function getAssetDataTemplate(
    id: string
): Promise<AssetDataTemplate> {
    const res = await apiClient.get(`/${EntityName.AssetDataTemplate}/${id}`);

    return res.data;
}
