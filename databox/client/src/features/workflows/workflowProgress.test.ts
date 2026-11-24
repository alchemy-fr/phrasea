import {describe, expect, it} from 'vitest';
import {
    JobStatus,
    WorkflowStatus,
    type WorkflowDetail,
    type WorkflowJob,
} from '@/types/api';
import {computeWorkflowProgress} from './workflowProgress';

function run(...stages: Partial<WorkflowJob>[][]): WorkflowDetail {
    return {
        id: 'w1',
        name: 'asset-ingest:ws1',
        status: WorkflowStatus.Started,
        startedAt: '2026-11-24T10:00:00Z',
        stages: stages.map((jobs, i) => ({
            stage: i + 1,
            jobs: jobs.map((j, k) => ({
                jobId: `job-${i}-${k}`,
                name: `Job ${i}.${k}`,
                ...j,
            })),
        })),
    };
}

describe('computeWorkflowProgress', () => {
    it('counts nothing on a run without job', () => {
        expect(computeWorkflowProgress(run())).toEqual({
            total: 0,
            done: 0,
            running: [],
            failed: 0,
        });
    });

    it('leaves the disabled jobs out', () => {
        const progress = computeWorkflowProgress(
            run(
                [{status: JobStatus.Success}, {disabled: true}],
                [{disabled: true, status: JobStatus.Skipped}]
            )
        );

        expect(progress.total).toBe(1);
        expect(progress.done).toBe(1);
    });

    it('counts the jobs not triggered yet in the total only', () => {
        const progress = computeWorkflowProgress(
            run([{status: JobStatus.Success}], [{}, {}])
        );

        expect(progress.total).toBe(3);
        expect(progress.done).toBe(1);
        expect(progress.running).toEqual([]);
    });

    it('counts every outcome as done, skipped included', () => {
        const progress = computeWorkflowProgress(
            run([
                {status: JobStatus.Success},
                {status: JobStatus.Skipped},
                {status: JobStatus.Failure},
                {status: JobStatus.Error},
                {status: JobStatus.Cancelled},
            ])
        );

        expect(progress.total).toBe(5);
        expect(progress.done).toBe(5);
        expect(progress.failed).toBe(2);
    });

    it('lists the triggered and running jobs', () => {
        const progress = computeWorkflowProgress(
            run(
                [{status: JobStatus.Success}],
                [
                    {name: 'Thumbnail', status: JobStatus.Running},
                    {name: 'Preview', status: JobStatus.Triggered},
                    {},
                ]
            )
        );

        expect(progress.total).toBe(4);
        expect(progress.done).toBe(1);
        expect(progress.running.map(j => j.name)).toEqual([
            'Thumbnail',
            'Preview',
        ]);
    });
});
