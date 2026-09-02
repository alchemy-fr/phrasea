import {TargetParam} from '../types.ts';
import {apiClient} from '../init.ts';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';
import {EntityName} from './entities.ts';

export async function listTargetParams(): Promise<
    NormalizedCollectionResponse<TargetParam>
> {
    return getHydraCollection(
        (await apiClient.get(EntityName.TargetParam)).data
    );
}
export async function getTargetParamByTarget(
    targetId: string
): Promise<TargetParam> {
    return (
        await apiClient.get(`/${EntityName.Target}/${targetId}/target-param`)
    ).data;
}

export async function getTargetParam(id: string): Promise<TargetParam> {
    return (await apiClient.get(`/${EntityName.TargetParam}/${id}`)).data;
}

export async function putTargetParam(
    id: string,
    data: Partial<TargetParam>
): Promise<TargetParam> {
    return (await apiClient.patch(`/${EntityName.TargetParam}/${id}`, data))
        .data;
}

export async function postTargetParam(
    data: Partial<TargetParam>
): Promise<TargetParam> {
    return (await apiClient.post(`/${EntityName.TargetParam}`, data)).data;
}

export async function deleteTargetParam(id: string): Promise<void> {
    await apiClient.delete(`/${EntityName.TargetParam}/${id}`);
}
