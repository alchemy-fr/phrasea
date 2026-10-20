import {useTranslation} from 'react-i18next';
import {useQueryClient} from '@tanstack/react-query';
import {toast} from 'sonner';
import type {SavedSearchAutomation} from '@/types/api';
import {triggerSavedSearchAutomation} from '@/lib/api/automations';
import {useModals} from '@/components/modals/ModalProvider';
import {toastError} from '@/lib/utils/errors';
import {AutomationRunsDialog} from './AutomationRunsPanel';
import {automationRunsQueryKey, automationsQueryKey} from './queryKeys';

/**
 * Triggers an automation (or a dry run of it) and opens its history, where
 * the run shows up.
 */
export function useRunAutomation() {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const queryClient = useQueryClient();

    return async (automation: SavedSearchAutomation, dryRun: boolean) => {
        try {
            await triggerSavedSearchAutomation(automation.id, {dryRun});
            toast.success(
                dryRun
                    ? t('automation.run.dry_run_started', 'Dry run started')
                    : t('automation.run.started', 'Automation started')
            );
            void queryClient.invalidateQueries({
                queryKey: automationRunsQueryKey(automation.id),
            });
            void queryClient.invalidateQueries({
                queryKey: automationsQueryKey(automation.savedSearchId),
            });
            void openModal(AutomationRunsDialog, {automation});
        } catch (e) {
            toastError(e);
        }
    };
}
