import {WorkspaceIntegration} from "../types";
import {ApiCollectionResponse, getHydraCollection} from "./hydra";
import apiClient from "./api-client";

export const integrationNS = '/integrations';

export async function getWorkspaceIntegrations(workspaceId: string, assetId: string): Promise<ApiCollectionResponse<WorkspaceIntegration>> {
    const res = await apiClient.get(integrationNS, {
        params: {
            workspace: workspaceId,
            assetId,
        },
    });

    return getHydraCollection(res.data);
}

export async function runIntegrationAssetAction(action: string, integrationId: string, assetId: string, data?: object): Promise<any> {
    return (await apiClient.post(`/integrations/${integrationId}/assets/${assetId}/actions/${action}`, data)).data;
}
