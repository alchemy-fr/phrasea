import {moveToCollectionAction} from './MoveToCollectionForm';
import {addToCollectionAction} from './AddToCollectionForm';
import {attributeAction} from './AttributeActionForm';
import {tagsAction} from './TagsActionForm';
import {emailReportAction} from './EmailReportForm';
import {remoteExportAction} from './RemoteExportForm';
import {webhookAction} from './WebhookForm';
import {triggerWorkflowAction} from './TriggerWorkflowForm';
import {trashAction} from './TrashActionForm';
import {setPrivacyAction} from './SetPrivacyForm';
import type {ActionDefinition} from './types';

/**
 * Action types of the automations, as handled by the API
 * (the `TYPE` of each AutomationActionInterface implementation).
 */
export const actionRegistry = {
    move_to_collection: moveToCollectionAction,
    add_to_collection: addToCollectionAction,
    attribute: attributeAction,
    tags: tagsAction,
    email_report: emailReportAction,
    remote_export: remoteExportAction,
    webhook: webhookAction,
    trigger_workflow: triggerWorkflowAction,
    trash: trashAction,
    set_privacy: setPrivacyAction,
} satisfies Record<string, ActionDefinition>;

export type ActionType = keyof typeof actionRegistry;

export const actionTypes = Object.keys(actionRegistry) as ActionType[];

export function getActionDefinition(
    type: string
): ActionDefinition | undefined {
    return (actionRegistry as Record<string, ActionDefinition>)[type];
}
