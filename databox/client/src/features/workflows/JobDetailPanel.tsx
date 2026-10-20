'use client';

import type {CSSProperties} from 'react';
import Link from 'next/link';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {
    BanIcon,
    FastForwardIcon,
    RotateCcwIcon,
    SettingsIcon,
    XIcon,
} from 'lucide-react';
import type {WorkflowDetail, WorkflowJob} from '@/types/api';
import {getWorkspace} from '@/lib/api/collections';
import {joinRoutePath} from '@/lib/navigation/routePath';
import {routes} from '@/lib/routes';
import {Button} from '@/components/ui/button';
import {CopiableText, CopyButton} from '@/components/ui/copy-button';
import {
    DetailFields,
    DetailSection,
    JsonSection,
    RelativeDate,
} from './workflowDetails';
import {
    JobStatusDot,
    canRerunJob,
    formatJobDuration,
    jobStatusLabel,
} from './jobStatus';
import {findJob} from './workflowGraph';
import {workflowWorkspaceId} from './workflowLabel';

/**
 * Details of the job selected in the graph (the legacy `JobDetail`): status,
 * timing, skip reason, condition, inputs, outputs and errors, with the rerun
 * action and, for the job of an integration, the way to its settings.
 *
 * Docked beside the graph, `width` wide; over it on small screens.
 */
export function JobDetailPanel({
    job,
    workflow,
    width,
    onRerun,
    onClose,
}: {
    job: WorkflowJob;
    /** The run the job belongs to: its other jobs, its workspace */
    workflow: WorkflowDetail;
    width: number;
    onRerun: (jobId: string) => Promise<void>;
    onClose: () => void;
}) {
    const {t} = useTranslation();
    const integrationUrl = useIntegrationUrl(
        job,
        workflowWorkspaceId(workflow.name)
    );

    return (
        <aside
            data-testid="workflow-job-detail"
            style={{'--panel-width': `${width}px`} as CSSProperties}
            className="absolute inset-0 z-10 flex flex-col bg-card text-card-foreground sm:static sm:z-auto sm:w-(--panel-width) sm:shrink-0"
        >
            <div className="flex items-center gap-2 border-b px-4 py-3">
                <JobStatusDot status={job.status} />
                <h2 className="min-w-0 flex-1 truncate font-semibold">
                    {job.name}
                </h2>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    onClick={onClose}
                    aria-label={t('common.close', 'Close')}
                >
                    <XIcon />
                </Button>
            </div>
            <div className="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                <DetailFields
                    fields={[
                        [
                            t('workflow.field.status', 'Status'),
                            jobStatusLabel(job.status, t),
                        ],
                        [
                            t('workflow.field.duration', 'Duration'),
                            formatJobDuration(job.duration) ?? '-',
                        ],
                        [
                            t('workflow.field.number', '#'),
                            job.number?.toString() ?? '-',
                        ],
                        [
                            t('workflow.field.started_at', 'Started at'),
                            <RelativeDate key="start" date={job.startedAt} />,
                        ],
                        [
                            t('workflow.field.ended_at', 'Ended at'),
                            <RelativeDate key="end" date={job.endedAt} />,
                        ],
                    ]}
                />
                {canRerunJob(job) || integrationUrl ? (
                    <div className="flex flex-wrap gap-2">
                        {canRerunJob(job) ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => onRerun(job.jobId)}
                            >
                                <RotateCcwIcon /> {t('workflow.rerun', 'Rerun')}
                            </Button>
                        ) : null}
                        {integrationUrl ? (
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={integrationUrl}
                                    scroll={false}
                                    data-testid="workflow-job-edit-integration"
                                >
                                    <SettingsIcon />{' '}
                                    {t(
                                        'workflow.edit_integration',
                                        'Edit integration'
                                    )}
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                ) : null}
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <span>{t('workflow.field.job_id', 'Job ID')}</span>
                    <CopiableText value={job.jobId} />
                </div>
                {job.disabled ? (
                    <div className="flex items-start gap-2 rounded-md border border-destructive/40 bg-destructive/10 p-2 text-sm text-destructive">
                        <BanIcon className="mt-0.5 size-4 shrink-0" />
                        <span>
                            {job.disabledReason ||
                                t('workflow.job_disabled', 'Job disabled')}
                        </span>
                    </div>
                ) : null}
                {job.skipReason ? (
                    <div
                        data-testid="workflow-job-skip-reason"
                        className="flex items-start gap-2 rounded-md border bg-muted p-2 text-sm text-muted-foreground"
                    >
                        <FastForwardIcon className="mt-0.5 size-4 shrink-0" />
                        <span>{job.skipReason}</span>
                    </div>
                ) : null}
                {job.needs?.length ? (
                    <DetailSection
                        title={t('workflow.field.needs', 'Depends on')}
                    >
                        <ul className="space-y-1">
                            {job.needs.map(n => {
                                const need = findJob(workflow, n);

                                return (
                                    <li
                                        key={n}
                                        className="group/need flex items-center gap-2 text-sm"
                                        title={n}
                                    >
                                        <JobStatusDot status={need?.status} />
                                        <span className="min-w-0 flex-1 truncate">
                                            {need?.name ?? n}
                                        </span>
                                        <CopyButton
                                            value={n}
                                            className="opacity-0 group-hover/need:opacity-100 focus-visible:opacity-100"
                                        />
                                    </li>
                                );
                            })}
                        </ul>
                    </DetailSection>
                ) : null}
                {job.if ? (
                    <DetailSection title={t('workflow.field.if', 'Condition')}>
                        <pre className="overflow-auto rounded-md bg-muted p-3 font-mono text-xs whitespace-pre-wrap">
                            {job.if}
                        </pre>
                    </DetailSection>
                ) : null}
                {/* Only a job that ran has inputs and outputs, even empty */}
                {job.stateId ? (
                    <>
                        <JsonSection
                            title={t('workflow.field.inputs', 'Inputs')}
                            data={job.inputs}
                            empty={t('workflow.no_inputs', 'No input')}
                        />
                        <JsonSection
                            title={t('workflow.field.outputs', 'Outputs')}
                            data={job.outputs}
                            empty={t('workflow.no_outputs', 'No output')}
                        />
                    </>
                ) : null}
                {job.errors?.length ? (
                    <DetailSection
                        title={t('workflow.field.errors', 'Errors')}
                        actions={<CopyButton value={job.errors.join('\n\n')} />}
                    >
                        <ul className="space-y-2">
                            {job.errors.map((e, i) => (
                                <li key={i} className="group/error relative">
                                    <pre className="max-h-80 overflow-auto rounded-md bg-destructive/10 p-3 font-mono text-xs whitespace-pre-wrap text-destructive">
                                        {e}
                                    </pre>
                                    {/* The header one copies them all */}
                                    {job.errors!.length > 1 ? (
                                        <CopyButton
                                            value={e}
                                            className="absolute top-1 right-3 bg-card opacity-0 group-hover/error:opacity-100 focus-visible:opacity-100"
                                        />
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    </DetailSection>
                ) : null}
            </div>
        </aside>
    );
}

/**
 * The settings of the integration the job runs (`with.integrationId`, set by
 * the API on every integration job), in the workspace manage dialog — for
 * those allowed to edit the workspace, the only ones who see that tab.
 */
function useIntegrationUrl(
    job: WorkflowJob,
    workspaceId: string | undefined
): string | undefined {
    const integrationId = job.with?.integrationId;
    const wanted = typeof integrationId === 'string' && !!workspaceId;
    // Same key as the manage dialog: one request for both
    const workspace = useQuery({
        queryKey: ['workspace', workspaceId],
        queryFn: () => getWorkspace(workspaceId!),
        enabled: wanted,
    });

    if (!wanted || !workspace.data?.capabilities.edit) {
        return undefined;
    }

    return joinRoutePath(routes.workspaceManage(workspaceId, 'integrations'), [
        integrationId,
    ]);
}
