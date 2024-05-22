import {IntegrationData, WorkspaceIntegration} from '../types';
import {ApiCollectionResponse, getHydraCollection} from './hydra';
import apiClient from './api-client';
import {AxiosRequestConfig} from 'axios';

export const integrationNS = '/integrations';

export async function getWorkspaceIntegrations(
    workspaceId: string,
    fileId: string
): Promise<ApiCollectionResponse<WorkspaceIntegration>> {
    const res = await apiClient.get(integrationNS, {
        params: {
            fileId,
            workspace: workspaceId,
        },
    });

    return getHydraCollection(res.data);
}

export async function getWorkspaceIntegrationData(
    integrationId: string,
    next?: string,
    config?: AxiosRequestConfig
): Promise<ApiCollectionResponse<IntegrationData>> {
    const res = await apiClient.get(
        next || `${integrationNS}/${integrationId}/data`,
        config
    );

    return getHydraCollection(res.data);
}

export async function runIntegrationFileAction(
    action: string,
    integrationId: string,
    fileId: string,
    data?: Record<string, string | Blob>,
    file?: File
): Promise<any> {
    const config: AxiosRequestConfig = {};
    const formData: FormData = new FormData();
    if (file) {
        formData.append('file', file, file.name);
        if (data) {
            Object.keys(data).forEach(k => {
                formData.set(k, data[k]);
            });
        }
        config.headers = {
            'Content-Type': 'multipart/form-data',
        };
    }

    return (
        await apiClient.post(
            `/integrations/${integrationId}/files/${fileId}/actions/${action}`,
            file ? formData : data,
            config
        )
    ).data;
}
