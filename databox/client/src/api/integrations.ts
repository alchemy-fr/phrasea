import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';
import {
    IntegrationData,
    IntegrationToken,
    IntegrationType,
    WorkspaceIntegration,
} from '../types';
import {apiClient} from '../init.ts';
import {AxiosRequestConfig} from 'axios';

export const integrationNS = '/integrations';
export const integrationTypeNS = '/integration-types';

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
): Promise<NormalizedCollectionResponse<WorkspaceIntegration>> {
    const res = await apiClient.get(integrationNS, {
        params: {
            context,
            enabled: true,
            workspace: workspaceId,
            ...data,
        },
    });

    return getHydraCollection(res.data);
}

export async function getWorkspaceIntegrationData(
    integrationId: string,
    next?: string,
    config?: AxiosRequestConfig
): Promise<NormalizedCollectionResponse<IntegrationData>> {
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
): Promise<NormalizedCollectionResponse<IntegrationToken>> {
    const res = await apiClient.get(
        next || `${integrationNS}/${integrationId}/tokens`,
        config
    );

    return getHydraCollection(res.data);
}

export async function runIntegrationAction(
    action: string,
    integrationId: string,
    data?: Record<string, any>
): Promise<any> {
    const config: AxiosRequestConfig = {};

    return (
        await apiClient.post(
            `/integrations/${integrationId}/actions/${action}`,
            data,
            config
        )
    ).data;
}

export async function getWorkspaceIntegrations(
    workspaceId: string
): Promise<WorkspaceIntegration[]> {
    const res = await apiClient.get(integrationNS, {
        params: {
            workspace: workspaceId,
            limit: 100,
        },
    });

    return res.data['hydra:member'];
}

export async function getIntegrationType(id: string): Promise<IntegrationType> {
    return (
        await apiClient.get(`${integrationTypeNS}/${id.replace(/\./g, '--')}`)
    ).data;
}

export async function getIntegrationTypes(): Promise<IntegrationType[]> {
    const res = await apiClient.get(`${integrationTypeNS}`);

    return res.data['hydra:member'];
}

export async function putIntegration(
    id: string | undefined,
    data: Partial<WorkspaceIntegration>
): Promise<WorkspaceIntegration> {
    delete data.workspace;

    return (await apiClient.put(`${integrationNS}/${id}`, data)).data;
}

export async function postIntegration(
    data: WorkspaceIntegration
): Promise<WorkspaceIntegration> {
    return (await apiClient.post(integrationNS, data)).data;
}

export async function deleteIntegration(id: string): Promise<void> {
    await apiClient.delete(`${integrationNS}/${id}`);
}
