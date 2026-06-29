import {AssetPolicy} from '../types.ts';
import {apiClient} from '../init.ts';
import {EntityName, PaginationParams} from './types.ts';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';

export async function putAssetPolicy(
    id: string | undefined,
    data: Partial<AssetPolicy>
): Promise<AssetPolicy> {
    return (await apiClient.put(`/${EntityName.AssetPolicy}/${id}`, data)).data;
}

export async function postAssetPolicy(data: AssetPolicy): Promise<AssetPolicy> {
    return (await apiClient.post(`/${EntityName.AssetPolicy}`, data)).data;
}

export async function deleteAssetPolicy(id: string): Promise<void> {
    await apiClient.delete(`/${EntityName.AssetPolicy}/${id}`);
}

export async function getAssetPolicies({
    nextUrl,
    workspaceId,
}: {
    workspaceId: string;
} & PaginationParams): Promise<NormalizedCollectionResponse<AssetPolicy>> {
    const res = await apiClient.get(nextUrl ?? `/${EntityName.AssetPolicy}`, {
        params: {
            workspaceId,
        },
    });

    return getHydraCollection(res.data);
}
