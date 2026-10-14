import type {TFunction} from 'i18next';
import {JobStatus, WorkflowStatus} from '@/types/api';
import type {WorkflowDetail, WorkflowJob} from '@/types/api';
import {cn} from '@/lib/utils/cn';

/** Key of a job status, `none` for a job that was not triggered yet */
export type JobStatusKey =
    | 'triggered'
    | 'success'
    | 'failure'
    | 'skipped'
    | 'running'
    | 'error'
    | 'cancelled'
    | 'none';

const statusKeys: Record<JobStatus, JobStatusKey> = {
    [JobStatus.Triggered]: 'triggered',
    [JobStatus.Success]: 'success',
    [JobStatus.Failure]: 'failure',
    [JobStatus.Skipped]: 'skipped',
    [JobStatus.Running]: 'running',
    [JobStatus.Error]: 'error',
    [JobStatus.Cancelled]: 'cancelled',
};

export function jobStatusKey(status: JobStatus | undefined): JobStatusKey {
    return status === undefined ? 'none' : (statusKeys[status] ?? 'none');
}

export function jobStatusLabel(
    status: JobStatus | undefined,
    t: TFunction
): string {
    switch (jobStatusKey(status)) {
        case 'triggered':
            return t('workflow.job_status.triggered', 'Triggered');
        case 'success':
            return t('workflow.job_status.success', 'Success');
        case 'failure':
            return t('workflow.job_status.failure', 'Failure');
        case 'skipped':
            return t('workflow.job_status.skipped', 'Skipped');
        case 'running':
            return t('workflow.job_status.running', 'Running');
        case 'error':
            return t('workflow.job_status.error', 'Error');
        case 'cancelled':
            return t('workflow.job_status.cancelled', 'Cancelled');
        default:
            return t('workflow.job_status.none', 'Not started');
    }
}

const dotClasses: Record<JobStatusKey, string> = {
    triggered: 'bg-orange-400',
    success: 'bg-success',
    failure: 'bg-destructive',
    skipped: 'bg-muted-foreground/40',
    running: 'bg-warning',
    error: 'bg-background ring-2 ring-destructive ring-inset',
    cancelled: 'bg-muted-foreground',
    none: 'border border-dashed border-muted-foreground/60',
};

/**
 * Coloured dot of a job status, pulsing while the job runs (the legacy
 * `JobStatusIndicator`).
 */
export function JobStatusDot({
    status,
    className,
}: {
    status: JobStatus | undefined;
    className?: string;
}) {
    const key = jobStatusKey(status);

    return (
        <span
            data-status={key}
            className={cn('relative inline-flex size-3 shrink-0', className)}
        >
            {key === 'running' ? (
                <span className="absolute inset-0 animate-ping rounded-full bg-warning opacity-75" />
            ) : null}
            <span
                className={cn(
                    'relative inline-flex size-3 rounded-full',
                    dotClasses[key]
                )}
            />
        </span>
    );
}

/** A job can be rerun once it is over, like in the legacy client */
export function canRerunJob(job: WorkflowJob): boolean {
    return (
        job.status !== undefined &&
        job.status !== JobStatus.Running &&
        job.status !== JobStatus.Triggered
    );
}

export function canCancelWorkflow(workflow: WorkflowDetail): boolean {
    return ![
        WorkflowStatus.Cancelled,
        WorkflowStatus.Failure,
        WorkflowStatus.Success,
    ].includes(workflow.status);
}

/** Formatted duration from the API, without its `-` placeholder */
export function formatJobDuration(duration: string | undefined) {
    return duration && duration !== '-' ? duration : undefined;
}

export function hasData(data: unknown): boolean {
    if (data === null || data === undefined) {
        return false;
    }
    if (Array.isArray(data)) {
        return data.length > 0;
    }
    if (typeof data === 'object') {
        return Object.keys(data).length > 0;
    }

    return true;
}
