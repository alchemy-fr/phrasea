import {IntegrationData, IntegrationToken, WorkspaceIntegration} from '../types';
import {ApiCollectionResponse, getHydraCollection} from './hydra';
import apiClient from './api-client';
import {AxiosRequestConfig} from 'axios';

export const integrationNS = '/integrations';

export enum IntegrationType {
    File = 'file',
    Basket = 'basket',
}

export async function getWorkspaceFileIntegrations(
    workspaceId: string,
    fileId: string
): Promise<ApiCollectionResponse<WorkspaceIntegration>> {
    return getIntegrationsOfType(IntegrationType.File, workspaceId, fileId);
}

export async function getIntegrationsOfType(
    type: IntegrationType,
    workspaceId: string | undefined,
    objectId?: string,
): Promise<ApiCollectionResponse<WorkspaceIntegration>> {
    const res = await apiClient.get(integrationNS, {
        params: {
            type,
            objectId,
            workspaceId,
        },
    });

    return getHydraCollection(res.data);
}


export async function getBasketIntegrations(
    basketId?: string,
): Promise<ApiCollectionResponse<WorkspaceIntegration>> {
    return getIntegrationsOfType(IntegrationType.Basket, undefined, basketId);
}

export async function getWorkspaceIntegrationData(
    type: IntegrationType,
    integrationId: string,
    next?: string,
    config?: AxiosRequestConfig
): Promise<ApiCollectionResponse<IntegrationData>> {
    const res = await apiClient.get(
        next || `${integrationNS}/${integrationId}/${type}-data`,
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

export async function runBasketIntegrationAction(
    action: string,
    integrationId: string,
    basketId: string,
    data?: Record<string, any>,
): Promise<any> {
    const config: AxiosRequestConfig = {};

    return (
        await apiClient.post(
            `/integrations/${integrationId}/baskets/${basketId}/actions/${action}`,
            data,
            config
        )
    ).data;
}
