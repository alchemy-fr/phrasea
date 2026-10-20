export function automationsQueryKey(savedSearchId: string) {
    return ['saved-search-automations', savedSearchId];
}

export function automationRunsQueryKey(automationId: string) {
    return ['automation-runs', automationId];
}
