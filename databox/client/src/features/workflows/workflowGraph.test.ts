import {describe, expect, it} from 'vitest';
import {JobStatus, WorkflowStatus, type WorkflowDetail} from '@/types/api';
import {
    buildWorkflowGraph,
    findJob,
    graphLayout,
    jobNodeType,
    type JobNodeData,
} from './workflowGraph';
import {canRerunJob, hasData, jobStatusKey} from './jobStatus';

const workflow: WorkflowDetail = {
    id: 'w1',
    name: 'asset-ingest',
    status: WorkflowStatus.Started,
    startedAt: '2026-09-29T10:00:00+00:00',
    stages: [
        {
            stage: 1,
            jobs: [
                {jobId: 'a', name: 'A', status: JobStatus.Success},
                {jobId: 'b', name: 'B', status: JobStatus.Failure},
            ],
        },
        {
            stage: 2,
            jobs: [
                {jobId: 'c', name: 'C', needs: ['a', 'b']},
                {jobId: 'd', name: 'D', needs: ['a', 'unknown']},
            ],
        },
    ],
};

describe('buildWorkflowGraph', () => {
    it('lays jobs out in a column per stage', () => {
        const {nodes} = buildWorkflowGraph(workflow);
        const jobs = nodes.filter(n => n.type === jobNodeType);
        const pos = Object.fromEntries(jobs.map(n => [n.id, n.position]));
        const {nodeWidth, nodeHeight, stageXPadding, nodeYPadding} =
            graphLayout;

        expect(jobs.map(n => n.id)).toEqual(['a', 'b', 'c', 'd']);
        expect(pos.a.x).toBe(pos.b.x);
        expect(pos.c.x - pos.a.x).toBe(nodeWidth + 2 * stageXPadding);
        expect(pos.b.y - pos.a.y).toBe(nodeHeight + 2 * nodeYPadding);
        expect(pos.c.y).toBe(pos.a.y);
        // A stage label above each column
        expect(nodes.filter(n => n.type !== jobNodeType)).toHaveLength(2);
    });

    it('links each job to the jobs it needs, ignoring unknown ones', () => {
        const {nodes, edges} = buildWorkflowGraph(workflow);

        expect(edges.map(e => [e.source, e.target])).toEqual([
            ['a', 'c'],
            ['b', 'c'],
            ['a', 'd'],
        ]);
        const isDependency = Object.fromEntries(
            nodes
                .filter(n => n.type === jobNodeType)
                .map(n => [n.id, (n.data as JobNodeData).isDependency])
        );
        expect(isDependency).toEqual({a: true, b: true, c: false, d: false});
    });

    it('highlights the edges of the selected job', () => {
        const {nodes, edges} = buildWorkflowGraph(workflow, 'b');

        expect(nodes.find(n => n.id === 'b')?.selected).toBe(true);
        expect(nodes.find(n => n.id === 'a')?.selected).toBe(false);
        expect(edges.filter(e => e.animated).map(e => e.id)).toEqual(['b->c']);
    });
});

describe('workflow helpers', () => {
    it('finds a job across stages', () => {
        expect(findJob(workflow, 'd')?.name).toBe('D');
        expect(findJob(workflow, 'x')).toBeUndefined();
        expect(findJob(undefined, 'a')).toBeUndefined();
    });

    it('allows rerunning finished jobs only', () => {
        expect(canRerunJob({jobId: 'x', name: 'x'})).toBe(false);
        expect(
            canRerunJob({jobId: 'x', name: 'x', status: JobStatus.Triggered})
        ).toBe(false);
        expect(
            canRerunJob({jobId: 'x', name: 'x', status: JobStatus.Running})
        ).toBe(false);
        expect(
            canRerunJob({jobId: 'x', name: 'x', status: JobStatus.Skipped})
        ).toBe(true);
        expect(
            canRerunJob({jobId: 'x', name: 'x', status: JobStatus.Failure})
        ).toBe(true);
    });

    it('maps statuses and detects empty data', () => {
        expect(jobStatusKey(undefined)).toBe('none');
        expect(jobStatusKey(JobStatus.Error)).toBe('error');
        expect(hasData([])).toBe(false);
        expect(hasData({})).toBe(false);
        expect(hasData(null)).toBe(false);
        expect(hasData({a: 1})).toBe(true);
    });
});
