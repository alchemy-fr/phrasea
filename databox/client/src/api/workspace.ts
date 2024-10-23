import apiClient from './api-client';
import {Workspace} from '../types';
import {ApiCollectionResponse, getHydraCollection} from './hydra.ts';

export async function getWorkspace(id: string): Promise<Workspace> {
    const res = await apiClient.get(`/workspaces/${id}`);

    return res.data;
}

export async function getWorkspaces(): Promise<
    ApiCollectionResponse<Workspace>
> {
    const res = await apiClient.get('/workspaces');

    return getHydraCollection(res.data);
}
