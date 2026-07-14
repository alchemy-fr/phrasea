import {getHydraCollection} from '@alchemy/api';
import {apiClient} from '../init';
import {Target} from '../types.ts';
import type {QueryAndPaginationParams} from '@alchemy/phrasea-framework';
import {EntityName} from './entities.ts';

export async function listTargets({
    nextUrl,
    ...options
}: QueryAndPaginationParams) {
    return getHydraCollection<Target>(
        (
            await apiClient.get(nextUrl ?? EntityName.Target, {
                params: options,
            })
        ).data
    );
}

export async function getTarget(id: string) {
    return (await apiClient.get(`/${EntityName.Target}/${id}`)).data;
}
