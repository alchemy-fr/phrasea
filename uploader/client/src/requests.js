import apiClient from './lib/api';

export async function getFormSchema(targetId) {
    return (await apiClient.get(`/targets/${targetId}/form-schema`)).data;
}

export async function getTargets() {
    return (await apiClient.get(`/targets`)).data['hydra:member'];
}

export async function getTarget(id) {
    return (await apiClient.get(`/targets/${id}`)).data;
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
