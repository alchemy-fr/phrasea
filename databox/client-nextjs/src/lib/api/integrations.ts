import {api} from './http';
import {toPage} from './hydra';
import {
    AttributeFilterRule,
    EntityName,
    HydraCollection,
    IntegrationData,
    IntegrationToken,
    IntegrationType,
    Page,
    TagFilterRule,
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

export async function getIntegrationData(
    integrationId: string,
    url?: string,
    signal?: AbortSignal
): Promise<Page<IntegrationData>> {
    return toPage(
        await api.get<HydraCollection<IntegrationData>>(
            url ?? `/${EntityName.Integration}/${integrationId}/data`,
            {signal}
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

    return api.put<WorkspaceIntegration>(
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

export async function getTagFilterRules(options: {
    collectionId?: string;
    workspaceId?: string;
}): Promise<Page<TagFilterRule>> {
    return toPage(
        await api.get<HydraCollection<TagFilterRule>>('/tag-filter-rules', {
            params: options,
        })
    );
}

export function saveTagFilterRule(data: {
    id?: string;
    userId?: string;
    groupId?: string;
    collectionId?: string;
    workspaceId?: string;
    include?: string[];
    exclude?: string[];
}): Promise<TagFilterRule> {
    const {id, ...rest} = data;

    return id
        ? api.put<TagFilterRule>(`/tag-filter-rules/${id}`, rest)
        : api.post<TagFilterRule>('/tag-filter-rules', rest);
}

export function deleteTagFilterRule(id: string): Promise<void> {
    return api.delete(`/tag-filter-rules/${id}`);
}

export async function getAttributeFilterRules(
    workspaceId: string
): Promise<Page<AttributeFilterRule>> {
    return toPage(
        await api.get<HydraCollection<AttributeFilterRule>>(
            '/attribute-filter-rules',
            {
                params: {workspace: workspaceId},
            }
        )
    );
}

export function saveAttributeFilterRule(data: {
    id?: string;
    users?: string[];
    groups?: string[];
    workspace?: string;
    condition: string;
}): Promise<AttributeFilterRule> {
    const {id, ...rest} = data;

    return id
        ? api.put<AttributeFilterRule>(`/attribute-filter-rules/${id}`, rest)
        : api.post<AttributeFilterRule>('/attribute-filter-rules', rest);
}

export function deleteAttributeFilterRule(id: string): Promise<void> {
    return api.delete(`/attribute-filter-rules/${id}`);
}
