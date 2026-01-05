import {getHydraCollection} from '@alchemy/api';
import {apiClient} from '../init';
import {Target} from '../types.ts';

export const targetEntity = `targets`;

export async function listTargets() {
    return getHydraCollection<Target>(
        (await apiClient.get(`/${targetEntity}`)).data
    );
}

export async function getTarget(id: string) {
    return (await apiClient.get(`/${targetEntity}/${id}`)).data;
}
