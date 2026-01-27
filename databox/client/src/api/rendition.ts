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

export const renditionPolicyNS = '/rendition-policies';
export const renditionDefinitionNS = '/rendition-definitions';
export const renditionNS = '/renditions';

export async function getAssetRenditions(
    assetId: string
): Promise<NormalizedCollectionResponse<AssetRendition>> {
    const res = await apiClient.get(renditionNS, {
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
    return (await apiClient.post(renditionNS, data)).data;
}

export async function getRenditionDefinitions(
    options: GetOptions = {}
): Promise<NormalizedCollectionResponse<RenditionDefinition>> {
    const res = await apiClient.get(renditionDefinitionNS, {
        params: options,
    });

    return getHydraCollection(res.data);
}

export async function putRenditionPolicy(
    id: string | undefined,
    data: Partial<RenditionPolicy>
): Promise<RenditionPolicy> {
    return (await apiClient.put(`${renditionPolicyNS}/${id}`, data)).data;
}

export async function postRenditionPolicy(
    data: RenditionPolicy
): Promise<RenditionPolicy> {
    return (await apiClient.post(renditionPolicyNS, data)).data;
}

export async function putRenditionDefinition(
    id: string | undefined,
    data: RenditionDefinition
): Promise<RenditionDefinition> {
    // @ts-expect-error no workspace
    delete data.workspace;

    return (await apiClient.put(`${renditionDefinitionNS}/${id}`, data)).data;
}

export async function postRenditionDefinition(
    data: RenditionDefinition
): Promise<RenditionDefinition> {
    return (await apiClient.post(renditionDefinitionNS, data)).data;
}

export async function getRenditionPolicies(
    workspaceId: string
): Promise<NormalizedCollectionResponse<RenditionPolicy>> {
    const res = await apiClient.get(renditionPolicyNS, {
        params: {
            workspaceId,
        },
    });

    return getHydraCollection(res.data);
}

export async function getWorkspaceRenditionDefinitions(
    workspaceId: string
): Promise<RenditionDefinition[]> {
    const res = await apiClient.get(renditionDefinitionNS, {
        params: {
            workspaceId,
        },
    });

    return res.data['hydra:member'];
}

export async function deleteRenditionPolicy(id: string): Promise<void> {
    await apiClient.delete(`${renditionPolicyNS}/${id}`);
}

export async function deleteRendition(id: string): Promise<void> {
    await apiClient.delete(`${renditionNS}/${id}`);
}

export async function deleteRenditionDefinition(id: string): Promise<void> {
    await apiClient.delete(`${renditionDefinitionNS}/${id}`);
}
