import {AttributeClass, AttributeDefinition} from "../types";
import apiClient from "./api-client";
import {ApiCollectionResponse, getHydraCollection} from "./hydra";
import {SelectOption} from "../components/Form/RSelect";

export const attributeClassNS = '/attribute-classes';
export const attributeDefinitionNS = '/attribute-definitions';

export async function putAttributeDefinition(
    id: string | undefined,
    data: AttributeDefinition
): Promise<AttributeDefinition> {
    return ((await apiClient.put(`${attributeDefinitionNS}/${id}`, data)).data);
}

export async function postAttributeDefinition(
    data: AttributeDefinition
): Promise<AttributeDefinition> {
    return (await apiClient.post(attributeDefinitionNS, data)).data;
}

export async function putAttributeClass(
    id: string | undefined,
    data: AttributeClass
): Promise<AttributeClass> {
    return ((await apiClient.put(`${attributeClassNS}/${id}`, data)).data);
}

export async function postAttributeClass(
    data: AttributeClass
): Promise<AttributeClass> {
    return (await apiClient.post(attributeClassNS, data)).data;
}

export async function getAttributeClasses(
    workspaceId: string
): Promise<ApiCollectionResponse<AttributeClass>> {
    const res = await apiClient.get(attributeClassNS, {
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
    const res = await apiClient.get(attributeClassNS, {
        params: {
            workspaceId,
        }
    });

    return res.data['hydra:member'];
}

export async function getWorkspaceAttributeDefinitions(workspaceId: string): Promise<AttributeDefinition[]> {
    const res = await apiClient.get(attributeDefinitionNS, {
        params: {
            workspaceId,
        }
    });

    return res.data['hydra:member'];
}

export async function deleteAttributeDefinition(id: string): Promise<void> {
    await apiClient.delete(`${attributeDefinitionNS}/${id}`);
}

export async function deleteAttributeClass(id: string): Promise<void> {
    await apiClient.delete(`${attributeClassNS}/${id}`);
}

export enum AttributeType {
    Text = 'text',
    Date = 'date',
    DateTime = 'date_time',
}
