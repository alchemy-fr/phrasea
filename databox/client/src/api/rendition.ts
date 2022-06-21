import {AttributeClass, RenditionClass, RenditionDefinition} from "../types";
import {ApiCollectionResponse, getHydraCollection} from "./hydra";
import apiClient from "./api-client";

type GetOptions = {
    workspaceIds?: string[];
}

export async function getRenditionDefinitions(options: GetOptions = {}): Promise<ApiCollectionResponse<RenditionDefinition>> {
    const res = await apiClient.get('/rendition-definitions', {
        params: options,
    });

    return getHydraCollection(res.data);
}

export async function putRenditionClass(
    id: string | undefined,
    data: RenditionClass
): Promise<RenditionClass> {
    return ((await apiClient.put(`/rendition-classes/${id}`, data)).data);
}

export async function postRenditionClass(
    data: RenditionClass
): Promise<RenditionClass> {
    return (await apiClient.post(`/rendition-classes`, data)).data;
}

export async function putRenditionDefinition(
    id: string | undefined,
    data: RenditionDefinition
): Promise<RenditionDefinition> {
    return ((await apiClient.put(`/rendition-definitions/${id}`, data)).data);
}

export async function postRenditionDefinition(
    data: RenditionDefinition
): Promise<RenditionDefinition> {
    return (await apiClient.post(`/rendition-definitions`, data)).data;
}

export async function getRenditionClasses(workspaceId: string): Promise<RenditionClass[]> {
    const res = await apiClient.get(`/rendition-classes`, {
        params: {
            workspaceId,
        }
    });

    return res.data['hydra:member'];
}

export async function getWorkspaceRenditionDefinitions(workspaceId: string): Promise<RenditionDefinition[]> {
    const res = await apiClient.get(`/rendition-definitions`, {
        params: {
            workspaceId,
        }
    });

    return res.data['hydra:member'];
}

export async function deleteRenditionClass(id: string): Promise<void> {
    await apiClient.delete(`/rendition-classes/${id}`);
}

export async function deleteRenditionDefinition(id: string): Promise<void> {
    await apiClient.delete(`/rendition-definitions/${id}`);
}
