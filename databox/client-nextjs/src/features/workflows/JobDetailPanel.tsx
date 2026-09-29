'use client';

import {useTranslation} from 'react-i18next';
import {BanIcon, RotateCcwIcon, XIcon} from 'lucide-react';
import type {WorkflowJob} from '@/types/api';
import {Button} from '@/components/ui/button';
import {CopiableText} from '@/components/ui/copy-button';
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
    hasData,
    jobStatusLabel,
} from './jobStatus';

/**
 * Details of the job selected in the graph (the legacy `JobDetail`): status,
 * timing, condition, inputs, outputs and errors, with the rerun action.
 */
export function JobDetailPanel({
    job,
    onRerun,
    onClose,
}: {
    job: WorkflowJob;
    onRerun: (jobId: string) => Promise<void>;
    onClose: () => void;
}) {
    const {t} = useTranslation();

    return (
        <aside
            data-testid="workflow-job-detail"
            className="absolute inset-y-0 right-0 z-10 flex w-full flex-col border-l bg-card text-card-foreground shadow-lg sm:w-[28rem]"
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
                {canRerunJob(job) ? (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onRerun(job.jobId)}
                    >
                        <RotateCcwIcon /> {t('workflow.rerun', 'Rerun')}
                    </Button>
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
                {job.needs?.length ? (
                    <DetailSection
                        title={t('workflow.field.needs', 'Depends on')}
                    >
                        <ul className="flex flex-wrap gap-1">
                            {job.needs.map(n => (
                                <li
                                    key={n}
                                    className="rounded-md bg-muted px-2 py-0.5 font-mono text-xs"
                                >
                                    {n}
                                </li>
                            ))}
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
                {hasData(job.inputs) ? (
                    <JsonSection
                        title={t('workflow.field.inputs', 'Inputs')}
                        data={job.inputs}
                    />
                ) : null}
                {hasData(job.outputs) ? (
                    <JsonSection
                        title={t('workflow.field.outputs', 'Outputs')}
                        data={job.outputs}
                    />
                ) : null}
                {job.errors?.length ? (
                    <DetailSection title={t('workflow.field.errors', 'Errors')}>
                        <ul className="space-y-2">
                            {job.errors.map((e, i) => (
                                <li key={i}>
                                    <pre className="max-h-80 overflow-auto rounded-md bg-destructive/10 p-3 font-mono text-xs whitespace-pre-wrap text-destructive">
                                        {e}
                                    </pre>
                                </li>
                            ))}
                        </ul>
                    </DetailSection>
                ) : null}
            </div>
        </aside>
    );
}
