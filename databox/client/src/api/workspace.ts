import {apiClient} from '../init.ts';
import {Workspace} from '../types';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';
import {Entity} from './types.ts';

export async function getWorkspace(id: string): Promise<Workspace> {
    const res = await apiClient.get(`/${Entity.Workspace}/${id}`);

    return res.data;
}

export async function getWorkspaces(): Promise<
    NormalizedCollectionResponse<Workspace>
> {
    const res = await apiClient.get(`/${Entity.Workspace}`);

    return getHydraCollection(res.data);
}
