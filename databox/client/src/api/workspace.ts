import {apiClient} from '../init.ts';
import {Workspace} from '../types';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';
import {EntityName} from './types.ts';
import {QueryAndPaginationParams} from '@alchemy/phrasea-framework';

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
