import {FormSchema} from '../types.ts';
import {apiClient} from '../init.ts';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';

export async function listFormSchemas(): Promise<
    NormalizedCollectionResponse<FormSchema>
> {
    return getHydraCollection((await apiClient.get(`form-schemas`)).data);
}
export async function getFormSchemaByTarget(
    targetId: string
): Promise<FormSchema> {
    return (await apiClient.get(`/targets/${targetId}/form-schema`)).data;
}

export async function getFormSchema(id: string): Promise<FormSchema> {
    return (await apiClient.get(`/form-schemas/${id}`)).data;
}

export async function putFormSchema(
    id: string,
    data: Partial<FormSchema>
): Promise<FormSchema> {
    return (await apiClient.put(`/form-schemas/${id}`, data)).data;
}

export async function postFormSchema(
    data: Partial<FormSchema>
): Promise<FormSchema> {
    return (await apiClient.post(`/form-schemas`, data)).data;
}

export async function deleteFormSchema(id: string): Promise<void> {
    await apiClient.delete(`/form-schemas/${id}`);
}
