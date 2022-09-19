import {WorkspaceIntegration} from "../types";
import {ApiCollectionResponse, getHydraCollection} from "./hydra";
import apiClient from "./api-client";

export const integrationNS = '/integrations';

export async function getWorkspaceIntegrations(fileId: string): Promise<ApiCollectionResponse<WorkspaceIntegration>> {
    const res = await apiClient.get(integrationNS, {
        params: {
            fileId,
        },
    });

    return getHydraCollection(res.data);
}

export async function runIntegrationFileAction(action: string, integrationId: string, fileId: string, data?: object): Promise<any> {
    return (await apiClient.post(`/integrations/${integrationId}/files/${fileId}/actions/${action}`, data)).data;
}
