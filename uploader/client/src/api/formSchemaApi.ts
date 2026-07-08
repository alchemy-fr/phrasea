import {FormSchema} from '../types.ts';
import {apiClient} from '../init.ts';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';
import {EntityName} from './entities.ts';

export async function listFormSchemas(): Promise<
    NormalizedCollectionResponse<FormSchema>
> {
    return getHydraCollection(
        (await apiClient.get(EntityName.FormSchema)).data
    );
}
export async function getFormSchemaByTarget(
    targetId: string
): Promise<FormSchema> {
    return (
        await apiClient.get(`/${EntityName.Target}/${targetId}/form-schema`)
    ).data;
}

export async function getFormSchema(id: string): Promise<FormSchema> {
    return (await apiClient.get(`/${EntityName.FormSchema}/${id}`)).data;
}

export async function putFormSchema(
    id: string,
    data: Partial<FormSchema>
): Promise<FormSchema> {
    return (await apiClient.put(`/${EntityName.FormSchema}/${id}`, data)).data;
}

export async function postFormSchema(
    data: Partial<FormSchema>
): Promise<FormSchema> {
    return (await apiClient.post(`/${EntityName.FormSchema}`, data)).data;
}

export async function deleteFormSchema(id: string): Promise<void> {
    await apiClient.delete(`/${EntityName.FormSchema}/${id}`);
}
