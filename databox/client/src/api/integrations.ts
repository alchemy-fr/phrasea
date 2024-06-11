import {
    IntegrationData,
    IntegrationToken,
    WorkspaceIntegration,
} from '../types';
import {ApiCollectionResponse, getHydraCollection} from './hydra';
import apiClient from './api-client';
import {AxiosRequestConfig} from 'axios';

export const integrationNS = '/integrations';

export enum IntegrationContext {
    AssetView = 'asset-view',
    Basket = 'basket',
}

export enum ObjectType {
    File = 'file',
    Basket = 'basket',
}

export async function getIntegrationsOfContext(
    context: IntegrationContext,
    workspaceId?: string | undefined,
    data: Record<string, any> = {}
): Promise<ApiCollectionResponse<WorkspaceIntegration>> {
    const res = await apiClient.get(integrationNS, {
        params: {
            context,
            workspaceId,
            ...data,
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

export async function getIntegrationTokens(
    integrationId: string,
    next?: string,
    config?: AxiosRequestConfig
): Promise<ApiCollectionResponse<IntegrationToken>> {
    const res = await apiClient.get(
        next || `${integrationNS}/${integrationId}/tokens`,
        config
    );

    return getHydraCollection(res.data);
}

export async function runIntegrationAction(
    action: string,
    integrationId: string,
    data?: Record<string, any>,
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
            `/integrations/${integrationId}/actions/${action}`,
            file ? formData : data,
            config
        )
    ).data;
}
