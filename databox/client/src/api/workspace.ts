import {apiClient} from '../init.ts';
import {Workspace} from '../types';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';
import {EntityName} from './types.ts';
import {QueryAndPaginationParams} from '@alchemy/phrasea-framework';
import {databoxMultipartUpload} from './asset.ts';

export async function getWorkspace(id: string): Promise<Workspace> {
    const res = await apiClient.get(`/${EntityName.Workspace}/${id}`);

    return res.data;
}

export async function getWorkspaces({
    nextUrl,
}: QueryAndPaginationParams): Promise<NormalizedCollectionResponse<Workspace>> {
    const res = await apiClient.get(nextUrl ?? EntityName.Workspace);

    return getHydraCollection(res.data);
}

export async function signWorkspaceTerms(id: string): Promise<Workspace> {
    const res = await apiClient.post(
        `/${EntityName.Workspace}/${id}/terms/sign`,
        {}
    );

    return res.data;
}

export async function uploadWorkspaceTermsPdf(
    id: string,
    file: File
): Promise<Workspace> {
    const multipart = await databoxMultipartUpload(apiClient, file);
    const res = await apiClient.post(`/${EntityName.Workspace}/${id}/terms`, {
        multipart,
    });

    return res.data;
}

export async function deleteWorkspaceTermsPdf(id: string): Promise<void> {
    await apiClient.delete(`/${EntityName.Workspace}/${id}/terms`);
}

export async function uploadWorkspaceLogo(
    id: string,
    file: File
): Promise<Workspace> {
    const multipart = await databoxMultipartUpload(apiClient, file);
    const res = await apiClient.post(`/${EntityName.Workspace}/${id}/logo`, {
        multipart,
    });

    return res.data;
}

export async function deleteWorkspaceLogo(id: string): Promise<void> {
    await apiClient.delete(`/${EntityName.Workspace}/${id}/logo`);
}
