'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {BanIcon, ChevronDownIcon, RefreshCwIcon} from 'lucide-react';
import type {WorkflowDetail} from '@/types/api';
import {DialogHeader, DialogTitle} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {CopiableText} from '@/components/ui/copy-button';
import {workflowStatusBadge} from '@/features/assets/manage/tabs/AssetWorkflowTab';
import {cn} from '@/lib/utils/cn';
import {DetailFields, JsonSection, RelativeDate} from './workflowDetails';
import {canCancelWorkflow, formatJobDuration, hasData} from './jobStatus';

/**
 * Status and metadata of the workflow run, with its refresh and cancel
 * actions; the event, context and outputs unfold below (the legacy
 * `WorkflowHeader`).
 */
export function WorkflowHeader({
    workflow,
    onRefresh,
    onCancel,
}: {
    workflow: WorkflowDetail;
    onRefresh: () => Promise<unknown>;
    onCancel: () => Promise<void>;
}) {
    const {t} = useTranslation();
    const [expanded, setExpanded] = useState(false);
    const hasDetails =
        !!workflow.event ||
        hasData(workflow.context) ||
        hasData(workflow.outputs);
    const toggle = () => setExpanded(e => !e);

    return (
        <div className="border-b">
            <DialogHeader className="gap-3 px-6 pt-5 pr-12 pb-4">
                <div className="flex flex-wrap items-center gap-3">
                    {hasDetails ? (
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            onClick={toggle}
                            aria-expanded={expanded}
                            aria-label={
                                expanded
                                    ? t('workflow.hide_details', 'Hide details')
                                    : t('workflow.show_details', 'Show details')
                            }
                        >
                            <ChevronDownIcon
                                className={cn(
                                    'transition-transform',
                                    expanded && 'rotate-180'
                                )}
                            />
                        </Button>
                    ) : null}
                    <DialogTitle className="min-w-0 flex-1 truncate">
                        {workflow.name}
                    </DialogTitle>
                    {workflowStatusBadge(workflow.status, t)}
                    <Button variant="ghost" size="sm" onClick={onRefresh}>
                        <RefreshCwIcon /> {t('common.refresh', 'Refresh')}
                    </Button>
                    {canCancelWorkflow(workflow) ? (
                        <Button variant="outline" size="sm" onClick={onCancel}>
                            <BanIcon /> {t('common.cancel', 'Cancel')}
                        </Button>
                    ) : null}
                </div>
                <DetailFields
                    fields={[
                        [
                            t('workflow.field.id', 'ID'),
                            <CopiableText key="id" value={workflow.id} />,
                        ],
                        [
                            t('workflow.field.event', 'Event'),
                            workflow.event ? (
                                <button
                                    key="event"
                                    type="button"
                                    className="cursor-pointer hover:underline"
                                    onClick={toggle}
                                >
                                    {workflow.event.name}
                                </button>
                            ) : (
                                '-'
                            ),
                        ],
                        [
                            t('workflow.field.duration', 'Duration'),
                            formatJobDuration(workflow.duration) ?? '-',
                        ],
                        [
                            t('workflow.field.started_at', 'Started at'),
                            <RelativeDate
                                key="start"
                                date={workflow.startedAt}
                            />,
                        ],
                        [
                            t('workflow.field.ended_at', 'Ended at'),
                            <RelativeDate key="end" date={workflow.endedAt} />,
                        ],
                    ]}
                />
            </DialogHeader>
            {expanded && hasDetails ? (
                <div
                    data-testid="workflow-details"
                    className="max-h-[40dvh] space-y-3 overflow-y-auto px-6 pb-4"
                >
                    {workflow.event ? (
                        <JsonSection
                            title={t(
                                'workflow.event_title',
                                'Event: {{name}}',
                                {
                                    name: workflow.event.name,
                                }
                            )}
                            data={workflow.event.inputs ?? {}}
                        />
                    ) : null}
                    {hasData(workflow.context) ? (
                        <JsonSection
                            title={t('workflow.field.context', 'Context')}
                            data={workflow.context}
                        />
                    ) : null}
                    {hasData(workflow.outputs) ? (
                        <JsonSection
                            title={t('workflow.field.outputs', 'Outputs')}
                            data={workflow.outputs}
                        />
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}
