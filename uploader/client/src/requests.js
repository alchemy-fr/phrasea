import {authenticatedRequest} from "./lib/api";

export function getFormSchema(targetId) {
    return authenticatedRequest({
        url: `/targets/${targetId}/form-schema`,
    });
}

export async function getTargets() {
    return (await authenticatedRequest({
        url: `/targets`,
    }))['hydra:member'];
}

export function getTarget(id) {
    return authenticatedRequest({
        url: `/targets/${id}`,
    });
}

export function getTargetParams(targetId) {
    return authenticatedRequest({
        url: `/target-params`,
        params: {
            target: targetId,
        },
    });
}
