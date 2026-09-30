import type {TFunction} from 'i18next';
import {JobStatus, WorkflowStatus} from '@/types/api';
import {Badge} from '@/components/ui/misc';
import {JobStatusDot} from './jobStatus';

/**
 * The workflows run on assets are named `<workflow>:<workspace id>`: the
 * workflow alone tells what the run is about.
 */
export function workflowBaseName(name: string): string {
    return name.split(':')[0];
}

/** Readable name of a workflow (`asset-ingest` → `Ingest`) */
export function workflowTypeLabel(name: string, t: TFunction): string {
    const base = workflowBaseName(name);
    switch (base) {
        case 'asset-ingest':
            return t('workflow.name.asset_ingest', 'Ingest');
        case 'attributes-update':
            return t('workflow.name.attributes_update', 'Attributes update');
        case 'incoming-uploader-file':
            return t('workflow.name.incoming_uploader_file', 'Upload');
        default: {
            const words = base.replace(/[-_]+/g, ' ').trim();

            return words.charAt(0).toUpperCase() + words.slice(1);
        }
    }
}

/** Title of a workflow run: `Ingest #2` (its rank on the asset) */
export function workflowTitle(
    workflow: {name: string; number?: number | null},
    t: TFunction
): string {
    const label = workflowTypeLabel(workflow.name, t);

    return workflow.number ? `${label} #${workflow.number}` : label;
}

export function workflowStatusBadge(status: WorkflowStatus, t: TFunction) {
    switch (status) {
        case WorkflowStatus.Success:
            return (
                <Badge variant="success">
                    {t('workflow.status.success', 'Success')}
                </Badge>
            );
        case WorkflowStatus.Failure:
            return (
                <Badge variant="destructive">
                    {t('workflow.status.failure', 'Failure')}
                </Badge>
            );
        case WorkflowStatus.Cancelled:
            return (
                <Badge variant="muted">
                    {t('workflow.status.cancelled', 'Cancelled')}
                </Badge>
            );
        default:
            return (
                <Badge variant="warning">
                    {t('workflow.status.started', 'Started')}
                </Badge>
            );
    }
}

const dotStatuses: Record<WorkflowStatus, JobStatus> = {
    [WorkflowStatus.Started]: JobStatus.Running,
    [WorkflowStatus.Success]: JobStatus.Success,
    [WorkflowStatus.Failure]: JobStatus.Failure,
    [WorkflowStatus.Cancelled]: JobStatus.Cancelled,
};

/** Compact status of a run, in lists: the job dot, pulsing while it runs */
export function WorkflowStatusDot({
    status,
    className,
}: {
    status: WorkflowStatus;
    className?: string;
}) {
    return <JobStatusDot status={dotStatuses[status]} className={className} />;
}
