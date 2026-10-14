import {api} from './http';
import {toPage} from './hydra';
import {
    EntityName,
    HydraCollection,
    IntegrationData,
    IntegrationToken,
    IntegrationType,
    Page,
    AttributeFilterRule,
    WorkspaceIntegration,
} from '@/types/api';
import {toIris} from '@/lib/utils/iri';

export enum IntegrationContext {
    AssetView = 'asset-view',
    Basket = 'basket',
}

export async function getIntegrationsOfContext(
    context: IntegrationContext,
    workspaceId?: string,
    extra: Record<string, unknown> = {}
): Promise<Page<WorkspaceIntegration>> {
    return toPage(
        await api.get<HydraCollection<WorkspaceIntegration>>(
            `/${EntityName.Integration}`,
            {
                params: {
                    context,
                    enabled: true,
                    workspace: workspaceId,
                    ...extra,
                },
            }
        )
    );
}

export async function getWorkspaceIntegrations(
    workspaceId: string
): Promise<Page<WorkspaceIntegration>> {
    return toPage(
        await api.get<HydraCollection<WorkspaceIntegration>>(
            `/${EntityName.Integration}`,
            {
                params: {workspace: workspaceId, limit: 100},
            }
        )
    );
}

/** Instance-wide integrations (not attached to a workspace) */
export async function getGlobalIntegrations(): Promise<
    Page<WorkspaceIntegration>
> {
    return toPage(
        await api.get<HydraCollection<WorkspaceIntegration>>(
            `/${EntityName.Integration}`,
            {
                params: {global: 1, limit: 100},
            }
        )
    );
}

export enum IntegrationObjectType {
    File = 'file',
    Basket = 'basket',
}

/**
 * Lists the data an integration stored. Pass the object (file, basket…)
 * it relates to: without it, the data of every object of the integration
 * comes back.
 */
export async function getIntegrationData(
    integrationId: string,
    object?: {type: IntegrationObjectType; id: string},
    signal?: AbortSignal
): Promise<Page<IntegrationData>> {
    return toPage(
        await api.get<HydraCollection<IntegrationData>>(
            `/${EntityName.Integration}/${integrationId}/data`,
            {
                signal,
                params: object
                    ? {objectType: object.type, objectId: object.id}
                    : undefined,
            }
        )
    );
}

export async function getIntegrationTokens(
    integrationId: string
): Promise<Page<IntegrationToken>> {
    return toPage(
        await api.get<HydraCollection<IntegrationToken>>(
            `/${EntityName.Integration}/${integrationId}/tokens`
        )
    );
}

export function runIntegrationAction(
    integrationId: string,
    action: string,
    data?: Record<string, unknown>
): Promise<any> {
    return api.post(
        `/${EntityName.Integration}/${integrationId}/actions/${action}`,
        data ?? {}
    );
}

export function getIntegrationType(id: string): Promise<IntegrationType> {
    return api.get<IntegrationType>(
        `/${EntityName.IntegrationType}/${id.replace(/\./g, '--')}`
    );
}

export async function getIntegrationTypes(): Promise<Page<IntegrationType>> {
    return toPage(
        await api.get<HydraCollection<IntegrationType>>(
            `/${EntityName.IntegrationType}`
        )
    );
}

export function putIntegration(
    id: string,
    data: Partial<WorkspaceIntegration>
): Promise<WorkspaceIntegration> {
    const {workspace: _w, ...rest} = data;

    return api.patch<WorkspaceIntegration>(
        `/${EntityName.Integration}/${id}`,
        toIris(rest)
    );
}

export function postIntegration(
    data: Partial<WorkspaceIntegration>
): Promise<WorkspaceIntegration> {
    return api.post<WorkspaceIntegration>(
        `/${EntityName.Integration}`,
        toIris(data)
    );
}

export function deleteIntegration(id: string): Promise<void> {
    return api.delete(`/${EntityName.Integration}/${id}`);
}

// Filter rules -------------------------------------------------------------

export async function getAttributeFilterRules(
    workspaceId: string
): Promise<Page<AttributeFilterRule>> {
    return toPage(
        await api.get<HydraCollection<AttributeFilterRule>>(
            '/attribute-filter-rules',
            {params: {workspaceId}}
        )
    );
}

export function saveAttributeFilterRule(data: {
    id?: string;
    /** No user nor group: the rule applies to everyone */
    userIds: string[];
    groupIds: string[];
    workspaceId: string;
    /** AQL condition the visible assets must match */
    condition: string;
}): Promise<AttributeFilterRule> {
    const {id, ...payload} = data;

    return id
        ? api.patch<AttributeFilterRule>(`/attribute-filter-rules/${id}`, payload)
        : api.post<AttributeFilterRule>('/attribute-filter-rules', payload);
}

export function deleteAttributeFilterRule(id: string): Promise<void> {
    return api.delete(`/attribute-filter-rules/${id}`);
}
