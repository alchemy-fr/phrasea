import {TargetParam} from '../types.ts';
import {apiClient} from '../init.ts';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';

const targetParamEntity = `target-params`;

export async function listTargetParams(): Promise<
    NormalizedCollectionResponse<TargetParam>
> {
    return getHydraCollection((await apiClient.get(targetParamEntity)).data);
}
export async function getTargetParamByTarget(
    targetId: string
): Promise<TargetParam> {
    return (await apiClient.get(`/targets/${targetId}/target-param`)).data;
}

export async function getTargetParam(id: string): Promise<TargetParam> {
    return (await apiClient.get(`/${targetParamEntity}/${id}`)).data;
}

export async function putTargetParam(
    id: string,
    data: Partial<TargetParam>
): Promise<TargetParam> {
    return (await apiClient.put(`/${targetParamEntity}/${id}`, data)).data;
}

export async function postTargetParam(
    data: Partial<TargetParam>
): Promise<TargetParam> {
    return (await apiClient.post(`/${targetParamEntity}`, data)).data;
}

export async function deleteTargetParam(id: string): Promise<void> {
    await apiClient.delete(`/${targetParamEntity}/${id}`);
}
