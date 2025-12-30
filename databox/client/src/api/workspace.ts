import apiClient from './api-client';
import {Workspace} from '../types';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';

export async function getWorkspace(id: string): Promise<Workspace> {
    const res = await apiClient.get(`/workspaces/${id}`);

    return res.data;
}

export async function getWorkspaces(): Promise<
    NormalizedCollectionResponse<Workspace>
> {
    const res = await apiClient.get('/workspaces');

    return getHydraCollection(res.data);
}
