import {
    AttributePolicy,
    AttributeDefinition,
    FieldType,
    AssetTypeFilter,
} from '../types';
import {apiClient} from '../init.ts';
import {NormalizedCollectionResponse, getHydraCollection} from '@alchemy/api';
import {PaginationParams} from './types.ts';

export const attributePolicyNS = '/attribute-policies';
export const attributeDefinitionNS = '/attribute-definitions';

export async function putAttributeDefinition(
    id: string | undefined,
    data: Partial<AttributeDefinition>
): Promise<AttributeDefinition> {
    delete data.workspace;

    return (await apiClient.put(`${attributeDefinitionNS}/${id}`, data)).data;
}

export async function postAttributeDefinition(
    data: AttributeDefinition
): Promise<AttributeDefinition> {
    return (await apiClient.post(attributeDefinitionNS, data)).data;
}

export async function putAttributePolicy(
    id: string | undefined,
    data: Partial<AttributePolicy>
): Promise<AttributePolicy> {
    delete data.workspace;

    return (await apiClient.put(`${attributePolicyNS}/${id}`, data)).data;
}

export async function postAttributePolicy(
    data: AttributePolicy
): Promise<AttributePolicy> {
    return (await apiClient.post(attributePolicyNS, data)).data;
}

export async function getAttributePolicies(
    workspaceId: string
): Promise<NormalizedCollectionResponse<AttributePolicy>> {
    const res = await apiClient.get(attributePolicyNS, {
        params: {
            workspaceId,
        },
    });

    return getHydraCollection<AttributePolicy>(res.data);
}

export async function getAttributeFieldTypes(): Promise<
    NormalizedCollectionResponse<FieldType>
> {
    return getHydraCollection((await apiClient.get(`/field-types`)).data);
}

export async function getWorkspaceAttributePolicies(
    workspaceId: string
): Promise<NormalizedCollectionResponse<AttributePolicy>> {
    return getHydraCollection(
        (
            await apiClient.get(attributePolicyNS, {
                params: {
                    workspaceId,
                },
            })
        ).data
    );
}

export async function getWorkspaceAttributeDefinitions({
    workspaceId,
    target,
    query,
    nextUrl,
    type,
}: {
    workspaceId: string;
    query?: string | null;
    type?: string | null;
    target: AssetTypeFilter;
} & PaginationParams): Promise<
    NormalizedCollectionResponse<AttributeDefinition>
> {
    const res = await apiClient.get(nextUrl ?? attributeDefinitionNS, {
        params: {
            workspaceId,
            target,
            name: query,
            type,
            limit: 100,
        },
    });

    return getHydraCollection<AttributeDefinition>(res.data);
}

export async function getAttributeDefinitions(): Promise<
    NormalizedCollectionResponse<AttributeDefinition>
> {
    const res = await apiClient.get(attributeDefinitionNS, {
        params: {
            limit: 1000,
        },
    });

    return getHydraCollection<AttributeDefinition>(res.data);
}

export async function deleteAttributeDefinition(id: string): Promise<void> {
    await apiClient.delete(`${attributeDefinitionNS}/${id}`);
}

export async function deleteAttributePolicy(id: string): Promise<void> {
    await apiClient.delete(`${attributePolicyNS}/${id}`);
}
