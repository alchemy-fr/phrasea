import {
    AssetRendition,
    RenditionPolicy,
    RenditionDefinition,
    AssetType,
} from '../types';
import {NormalizedCollectionResponse, getHydraCollection} from '@alchemy/api';
import {apiClient} from '../init.ts';
import type {MultipartUpload} from '@alchemy/api';
import {SourceFileInput} from './file.ts';
import {EntityName} from './types.ts';

type GetOptions = {
    workspaceIds?: string[];
    target?: AssetType | undefined;
    [key: string]: any;
};

export enum RenditionBuildMode {
    NONE = 0,
    PICK_SOURCE = 1,
    CUSTOM = 2,
}

export async function getAssetRenditions(
    assetId: string
): Promise<NormalizedCollectionResponse<AssetRendition>> {
    const res = await apiClient.get(`/${EntityName.Rendition}`, {
        params: {
            assetId,
        },
    });

    return getHydraCollection(res.data);
}

type RenditionInput = {
    name?: string | undefined;
    definitionId?: string | undefined;
    sourceFile?: SourceFileInput;
    sourceFileId?: string | undefined;
    assetId: string;
    substituted?: boolean;
    force?: boolean;
    multipart?: MultipartUpload;
};

export async function postRendition(
    data: RenditionInput
): Promise<AssetRendition> {
    return (await apiClient.post(`/${EntityName.Rendition}`, data)).data;
}

export async function getRenditionDefinitions(
    options: GetOptions = {}
): Promise<NormalizedCollectionResponse<RenditionDefinition>> {
    const res = await apiClient.get(`/${EntityName.RenditionDefinition}`, {
        params: options,
    });

    return getHydraCollection(res.data);
}

export async function putRenditionPolicy(
    id: string | undefined,
    data: Partial<RenditionPolicy>
): Promise<RenditionPolicy> {
    return (await apiClient.put(`/${EntityName.RenditionPolicy}/${id}`, data))
        .data;
}

export async function postRenditionPolicy(
    data: RenditionPolicy
): Promise<RenditionPolicy> {
    return (await apiClient.post(`/${EntityName.RenditionPolicy}`, data)).data;
}

export async function putRenditionDefinition(
    id: string | undefined,
    data: RenditionDefinition
): Promise<RenditionDefinition> {
    // @ts-expect-error no workspace
    delete data.workspace;

    return (
        await apiClient.put(`/${EntityName.RenditionDefinition}/${id}`, data)
    ).data;
}

export async function postRenditionDefinition(
    data: RenditionDefinition
): Promise<RenditionDefinition> {
    return (await apiClient.post(`/${EntityName.RenditionDefinition}`, data))
        .data;
}

export async function getRenditionPolicies(
    workspaceId: string
): Promise<NormalizedCollectionResponse<RenditionPolicy>> {
    const res = await apiClient.get(`/${EntityName.RenditionPolicy}`, {
        params: {
            workspaceId,
        },
    });

    return getHydraCollection(res.data);
}

export async function getWorkspaceRenditionDefinitions(
    workspaceId: string
): Promise<RenditionDefinition[]> {
    const res = await apiClient.get(`/${EntityName.RenditionDefinition}`, {
        params: {
            workspaceId,
        },
    });

    return res.data['hydra:member'];
}

export async function deleteRenditionPolicy(id: string): Promise<void> {
    await apiClient.delete(`/${EntityName.RenditionPolicy}/${id}`);
}

export async function deleteRendition(id: string): Promise<void> {
    await apiClient.delete(`/${EntityName.Rendition}/${id}`);
}

export async function deleteRenditionDefinition(id: string): Promise<void> {
    await apiClient.delete(`/${EntityName.RenditionDefinition}/${id}`);
}
