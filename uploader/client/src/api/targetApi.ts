import {getHydraCollection} from '@alchemy/api';
import {apiClient} from '../init';
import {FormSchema, Target} from '../types.ts';

export async function getFormSchema(targetId: string): Promise<FormSchema> {
    return (await apiClient.get(`/targets/${targetId}/form-schema`)).data;
}

export async function getTargets() {
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
