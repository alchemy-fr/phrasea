import {JobStatus} from '@/types/api';
import type {WorkflowDetail, WorkflowJob} from '@/types/api';

export type WorkflowProgress = {
    /** Jobs of the run, the disabled ones left out */
    total: number;
    /** Jobs over, whatever their outcome (skipped included) */
    done: number;
    /** Jobs triggered or running */
    running: WorkflowJob[];
    /** Jobs that failed or errored */
    failed: number;
};

const doneStatuses = new Set<JobStatus>([
    JobStatus.Success,
    JobStatus.Failure,
    JobStatus.Skipped,
    JobStatus.Error,
    JobStatus.Cancelled,
]);

const runningStatuses = new Set<JobStatus>([
    JobStatus.Running,
    JobStatus.Triggered,
]);

const failedStatuses = new Set<JobStatus>([JobStatus.Failure, JobStatus.Error]);

/**
 * Progress of a run over its jobs. A job not triggered yet counts in the total
 * only: the run is complete once every enabled job is over.
 */
export function computeWorkflowProgress(
    detail: WorkflowDetail
): WorkflowProgress {
    const progress: WorkflowProgress = {
        total: 0,
        done: 0,
        running: [],
        failed: 0,
    };

    for (const stage of detail.stages) {
        for (const job of stage.jobs) {
            if (job.disabled) {
                continue;
            }
            progress.total++;
            if (job.status === undefined) {
                continue;
            }
            if (doneStatuses.has(job.status)) {
                progress.done++;
            }
            if (runningStatuses.has(job.status)) {
                progress.running.push(job);
            }
            if (failedStatuses.has(job.status)) {
                progress.failed++;
            }
        }
    }

    return progress;
}
