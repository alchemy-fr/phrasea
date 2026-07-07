import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';
import {
    IntegrationData,
    IntegrationToken,
    IntegrationType,
    WorkspaceIntegration,
} from '../types';
import {apiClient} from '../init.ts';
import {AxiosRequestConfig} from 'axios';
import {
    EntityName,
    PaginationParams,
    QueryAndPaginationParams,
} from './types.ts';

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
    const res = await apiClient.get(EntityName.Integration, {
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
        next || `${EntityName.Integration}/${integrationId}/data`,
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
        next || `${EntityName.Integration}/${integrationId}/tokens`,
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

export async function getWorkspaceIntegrations({
    workspaceId,
}: {workspaceId: string} & PaginationParams): Promise<
    NormalizedCollectionResponse<WorkspaceIntegration>
> {
    return getHydraCollection(
        (
            await apiClient.get(EntityName.Integration, {
                params: {
                    workspace: workspaceId,
                    limit: 100,
                },
            })
        ).data
    );
}

export async function getIntegrationType(id: string): Promise<IntegrationType> {
    return (
        await apiClient.get(
            `${EntityName.IntegrationType}/${id.replace(/\./g, '--')}`
        )
    ).data;
}

export async function getIntegrationTypes({
    nextUrl,
}: QueryAndPaginationParams): Promise<
    NormalizedCollectionResponse<IntegrationType>
> {
    return getHydraCollection(
        (await apiClient.get(nextUrl ?? EntityName.IntegrationType)).data
    );
}

export async function putIntegration(
    id: string | undefined,
    data: Partial<WorkspaceIntegration>
): Promise<WorkspaceIntegration> {
    delete data.workspace;

    return (await apiClient.put(`${EntityName.Integration}/${id}`, data)).data;
}

export async function postIntegration(
    data: WorkspaceIntegration
): Promise<WorkspaceIntegration> {
    return (await apiClient.post(EntityName.Integration, data)).data;
}

export async function deleteIntegration(id: string): Promise<void> {
    await apiClient.delete(`${EntityName.Integration}/${id}`);
}
