import {getHydraCollection} from '@alchemy/api';
import {apiClient} from '../init';
import {Target} from '../types.ts';

export async function listTargets() {
    return getHydraCollection<Target>((await apiClient.get(`/targets`)).data);
}

export async function getTarget(id: string) {
    return (await apiClient.get(`/targets/${id}`)).data;
}

export async function getTargetParams(targetId: string) {
    return getHydraCollection(
        (
            await apiClient.get(`/target-params`, {
                params: {
                    target: targetId,
                },
            })
        ).data
    );
}
