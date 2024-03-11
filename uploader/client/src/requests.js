import apiClient from './lib/apiClient';

export async function getFormSchema(targetId) {
    return (await apiClient.get(`/targets/${targetId}/form-schema`)).data;
}

export async function getTargets() {
    return (await apiClient.get(`/targets`)).data['hydra:member'];
}

const targetCache = {};

export async function getTarget(id) {
    if (targetCache[id]) {
        return targetCache[id];
    }

    return (targetCache[id] = (await apiClient.get(`/targets/${id}`)).data);
}

export async function getTargetParams(targetId) {
    return (
        await apiClient.get(`/target-params`, {
            params: {
                target: targetId,
            },
        })
    ).data['hydra:member'];
}
