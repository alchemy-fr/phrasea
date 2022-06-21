import {AttributeClass, AttributeDefinition} from "../types";
import apiClient from "./api-client";
import {ApiCollectionResponse, getHydraCollection} from "./hydra";
import {SelectOption} from "../components/Form/RSelect";

export async function putAttributeDefinition(
    id: string | undefined,
    data: AttributeDefinition
): Promise<AttributeDefinition> {
    return ((await apiClient.put(`/attribute-definitions/${id}`, data)).data);
}

export async function postAttributeDefinition(
    data: AttributeDefinition
): Promise<AttributeDefinition> {
    return (await apiClient.post(`/attribute-definitions`, data)).data;
}

export async function putAttributeClass(
    id: string | undefined,
    data: AttributeClass
): Promise<AttributeClass> {
    return ((await apiClient.put(`/attribute-classes/${id}`, data)).data);
}

export async function postAttributeClass(
    data: AttributeClass
): Promise<AttributeClass> {
    return (await apiClient.post(`/attribute-classes`, data)).data;
}

export async function getAttributeClasses(
    workspaceId: string
): Promise<ApiCollectionResponse<AttributeClass>> {
    const res = await apiClient.get(`/attribute-classes`, {
        params: {
            workspaceId,
        }
    });

    return getHydraCollection<AttributeClass>(res.data)
}

export async function getAttributeFieldTypes(): Promise<SelectOption[]> {
    return [
        {
            label: 'Text',
            value: 'text',
        },
        {
            label: 'Textarea',
            value: 'textarea',
        },
        {
            label: 'Number',
            value: 'number',
        },
        {
            label: 'Ip',
            value: 'ip',
        },
        {
            label: 'Date',
            value: 'date',
        },
        {
            label: 'Boolean',
            value: 'boolean',
        },
    ];
}

export async function getWorkspaceAttributeClasses(workspaceId: string): Promise<AttributeClass[]> {
    const res = await apiClient.get(`/attribute-classes`, {
        params: {
            workspaceId,
        }
    });

    return res.data['hydra:member'];
}

export async function getWorkspaceAttributeDefinitions(workspaceId: string): Promise<AttributeDefinition[]> {
    const res = await apiClient.get(`/attribute-definitions`, {
        params: {
            workspaceId,
        }
    });

    return res.data['hydra:member'];
}

export async function deleteAttributeDefinition(id: string): Promise<void> {
    await apiClient.delete(`/attribute-definitions/${id}`);
}

export async function deleteAttributeClass(id: string): Promise<void> {
    await apiClient.delete(`/attribute-classes/${id}`);
}
