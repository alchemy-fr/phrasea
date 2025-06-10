import {AttributeClass, AttributeDefinition, FieldType} from '../types';
import apiClient from './api-client';
import {ApiCollectionResponse, getHydraCollection} from './hydra';

export const attributeClassNS = '/attribute-classes';
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

export async function putAttributeClass(
    id: string | undefined,
    data: Partial<AttributeClass>
): Promise<AttributeClass> {
    delete data.workspace;

    return (await apiClient.put(`${attributeClassNS}/${id}`, data)).data;
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
        },
    });

    return getHydraCollection<AttributeClass>(res.data);
}

export async function getAttributeFieldTypes(): Promise<FieldType[]> {
    const res = await apiClient.get(`/field-types`);

    return res.data['hydra:member'];
}

export async function getWorkspaceAttributeClasses(
    workspaceId: string
): Promise<AttributeClass[]> {
    const res = await apiClient.get(attributeClassNS, {
        params: {
            workspaceId,
        },
    });

    return res.data['hydra:member'];
}

export async function getWorkspaceAttributeDefinitions(
    workspaceId: string
): Promise<AttributeDefinition[]> {
    const res = await apiClient.get(attributeDefinitionNS, {
        params: {
            workspaceId,
            limit: 100,
        },
    });

    return res.data['hydra:member'];
}

export async function getAttributeDefinitions(): Promise<
    AttributeDefinition[]
> {
    const res = await apiClient.get(attributeDefinitionNS, {
        params: {
            limit: 1000,
        },
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
    Boolean = 'boolean',
    Code = 'code',
    CollectionPath = 'collection_path',
    Color = 'color',
    Date = 'date',
    DateTime = 'date_time',
    Entity = 'entity',
    GeoPoint = 'geo_point',
    Html = 'html',
    Id = 'id',
    Ip = 'ip',
    Json = 'json',
    Keyword = 'keyword',
    Number = 'number',
    Privacy = 'privacy',
    Tag = 'tag',
    Text = 'text',
    Textarea = 'textarea',
    WebVtt = 'web_vtt',
    Workspace = 'workspace',
    Rendition = 'rendition',
    User = 'user',
}
