import {api} from './http';
import {toPage} from './hydra';
import {
    AutomationAction,
    AutomationRun,
    AutomationScope,
    EntityName,
    HydraCollection,
    Page,
    SavedSearchAutomation,
} from '@/types/api';

export type SavedSearchAutomationInput = {
    savedSearch?: string;
    name?: string;
    enabled?: boolean;
    /** Cron expression, an empty string for an automation run on demand only */
    schedule?: string;
    timezone?: string;
    scope?: AutomationScope;
    /** A secret left empty keeps its current value */
    actions?: AutomationAction[];
};

export type ResetCursorMode = 'empty' | 'current';

export async function getSavedSearchAutomations(
    savedSearchId: string
): Promise<SavedSearchAutomation[]> {
    const res = await api.get<HydraCollection<SavedSearchAutomation>>(
        `/${EntityName.SavedSearchAutomation}`,
        {params: {savedSearch: savedSearchId}}
    );

    return res.member;
}

export function getSavedSearchAutomation(
    id: string
): Promise<SavedSearchAutomation> {
    return api.get<SavedSearchAutomation>(
        `/${EntityName.SavedSearchAutomation}/${id}`
    );
}

export function postSavedSearchAutomation(
    data: SavedSearchAutomationInput
): Promise<SavedSearchAutomation> {
    return api.post<SavedSearchAutomation>(
        `/${EntityName.SavedSearchAutomation}`,
        data
    );
}

export function patchSavedSearchAutomation(
    id: string,
    data: SavedSearchAutomationInput
): Promise<SavedSearchAutomation> {
    return api.patch<SavedSearchAutomation>(
        `/${EntityName.SavedSearchAutomation}/${id}`,
        data
    );
}

export function deleteSavedSearchAutomation(id: string): Promise<void> {
    return api.delete(`/${EntityName.SavedSearchAutomation}/${id}`);
}

export function triggerSavedSearchAutomation(
    id: string,
    {dryRun = false}: {dryRun?: boolean} = {}
): Promise<AutomationRun> {
    return api.post<AutomationRun>(
        `/${EntityName.SavedSearchAutomation}/${id}/run`,
        {dryRun}
    );
}

export function resetAutomationCursor(
    id: string,
    mode: ResetCursorMode
): Promise<void> {
    return api.post<void>(
        `/${EntityName.SavedSearchAutomation}/${id}/reset-cursor`,
        {mode}
    );
}

export async function getAutomationRuns(
    automationId: string,
    {next}: {next?: string} = {}
): Promise<Page<AutomationRun>> {
    return toPage(
        await api.get<HydraCollection<AutomationRun>>(
            next ?? `/${EntityName.AutomationRun}`,
            {params: next ? undefined : {automation: automationId}}
        )
    );
}
