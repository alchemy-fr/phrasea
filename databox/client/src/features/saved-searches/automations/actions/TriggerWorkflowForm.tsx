'use client';

import {useTranslation} from 'react-i18next';
import {WorkflowIcon} from 'lucide-react';
import {FieldHelp} from '@/components/ui/input';
import type {ActionDefinition} from './types';

export function TriggerWorkflowForm() {
    const {t} = useTranslation();

    return (
        <FieldHelp>
            {t(
                'automation.action.trigger_workflow.help',
                'The ingest workflow of each asset (renditions, integrations…) is run again, on behalf of the owner of the search. Assets the owner is not allowed to edit are skipped.'
            )}
        </FieldHelp>
    );
}

export const triggerWorkflowAction: ActionDefinition = {
    label: t =>
        t('automation.action.trigger_workflow.label', 'Run the workflow'),
    icon: <WorkflowIcon />,
    Form: TriggerWorkflowForm,
    summary: (_config, t) =>
        t(
            'automation.action.trigger_workflow.summary',
            'Run the ingest workflow of the assets'
        ),
    defaultConfig: () => ({}),
    isComplete: () => true,
};
