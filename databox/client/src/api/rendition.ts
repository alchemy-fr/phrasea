import {AssetRendition, RenditionClass, RenditionDefinition} from '../types';
import {ApiCollectionResponse, getHydraCollection} from './hydra';
import apiClient from './api-client';

type GetOptions = {
    workspaceIds?: string[];
};

export const renditionClassNS = '/rendition-classes';
export const renditionDefinitionNS = '/rendition-definitions';
export const renditionNS = '/renditions';

export async function getAssetRenditions(
    assetId: string
): Promise<ApiCollectionResponse<AssetRendition>> {
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
    sourceFileId?: string | undefined;
    assetId: string;
};

export async function postRendition(
    data: RenditionInput
): Promise<AssetRendition> {
    return (await apiClient.post(renditionNS, data)).data;
}

export async function getRenditionDefinitions(
    options: GetOptions = {}
): Promise<ApiCollectionResponse<RenditionDefinition>> {
    const res = await apiClient.get(renditionDefinitionNS, {
        params: options,
    });

    return getHydraCollection(res.data);
}

export async function putRenditionClass(
    id: string | undefined,
    data: Partial<RenditionClass>
): Promise<RenditionClass> {
    return (await apiClient.put(`${renditionClassNS}/${id}`, data)).data;
}

export async function postRenditionClass(
    data: RenditionClass
): Promise<RenditionClass> {
    return (await apiClient.post(renditionClassNS, data)).data;
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

export async function getRenditionClasses(
    workspaceId: string
): Promise<ApiCollectionResponse<RenditionClass>> {
    const res = await apiClient.get(renditionClassNS, {
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

export async function deleteRenditionClass(id: string): Promise<void> {
    await apiClient.delete(`${renditionClassNS}/${id}`);
}

export async function deleteRenditionDefinition(id: string): Promise<void> {
    await apiClient.delete(`${renditionDefinitionNS}/${id}`);
}
