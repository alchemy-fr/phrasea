import type {Edge, Node} from 'reactflow';
import type {WorkflowDetail, WorkflowJob} from '@/types/api';

/**
 * Geometry of the graph, the one of the legacy `VisualWorkflow`: one column
 * per stage, its jobs stacked in the order of the plan.
 */
export const graphLayout = {
    nodeWidth: 300,
    nodeHeight: 55,
    stageXPadding: 30,
    nodeYPadding: 15,
    /** Height of the stage label above each column */
    stageLabelHeight: 20,
};

export type JobNodeData = {
    job: WorkflowJob;
    /** Other jobs need this one: it has an outgoing handle */
    isDependency: boolean;
};

export type StageNodeData = {
    stage: number;
};

export const jobNodeType = 'job';
export const stageNodeType = 'stage';

export function jobPosition(stageIndex: number, jobIndex: number) {
    const {nodeWidth, nodeHeight, stageXPadding, nodeYPadding} = graphLayout;

    return {
        x: stageXPadding * (1 + stageIndex * 2) + nodeWidth * stageIndex,
        y:
            graphLayout.stageLabelHeight +
            nodeYPadding * (1 + jobIndex * 2) +
            nodeHeight * jobIndex,
    };
}

/**
 * Nodes and edges of a workflow run: a node per job, an edge from each job to
 * the jobs that need it. The edges of the selected job are highlighted.
 */
export function buildWorkflowGraph(
    workflow: WorkflowDetail,
    selectedJobId?: string
): {nodes: Node<JobNodeData | StageNodeData>[]; edges: Edge[]} {
    const jobIds = new Set<string>();
    const dependencies = new Set<string>();
    workflow.stages.forEach(s =>
        s.jobs.forEach(j => {
            jobIds.add(j.jobId);
            j.needs?.forEach(n => dependencies.add(n));
        })
    );

    const nodes: Node<JobNodeData | StageNodeData>[] = [];
    const edges: Edge[] = [];

    workflow.stages.forEach((stage, stageIndex) => {
        const {x} = jobPosition(stageIndex, 0);
        nodes.push({
            id: `stage-${stageIndex}`,
            type: stageNodeType,
            position: {x, y: 0},
            data: {stage: stage.stage ?? stageIndex + 1},
            style: {width: graphLayout.nodeWidth},
            selectable: false,
            draggable: false,
            focusable: false,
            connectable: false,
        });

        stage.jobs.forEach((job, jobIndex) => {
            nodes.push({
                id: job.jobId,
                type: jobNodeType,
                position: jobPosition(stageIndex, jobIndex),
                data: {job, isDependency: dependencies.has(job.jobId)},
                style: {
                    width: graphLayout.nodeWidth,
                    height: graphLayout.nodeHeight,
                },
                selected: job.jobId === selectedJobId,
                draggable: false,
                connectable: false,
            });

            job.needs?.forEach(need => {
                if (!jobIds.has(need)) {
                    return;
                }
                const active =
                    selectedJobId !== undefined &&
                    (need === selectedJobId || job.jobId === selectedJobId);

                edges.push({
                    id: `${need}->${job.jobId}`,
                    source: need,
                    target: job.jobId,
                    className: active ? 'job-edge job-edge-active' : 'job-edge',
                    animated: active,
                    selected: active,
                    focusable: false,
                    updatable: false,
                });
            });
        });
    });

    return {nodes, edges};
}

export function findJob(
    workflow: WorkflowDetail | undefined,
    jobId: string | undefined
): WorkflowJob | undefined {
    if (!workflow || !jobId) {
        return undefined;
    }
    for (const stage of workflow.stages) {
        const job = stage.jobs.find(j => j.jobId === jobId);
        if (job) {
            return job;
        }
    }

    return undefined;
}
