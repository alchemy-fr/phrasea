'use client';

import {
    memo,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import {useTranslation} from 'react-i18next';
import ReactFlow, {
    Background,
    Controls,
    Handle,
    MiniMap,
    Position,
    type Node,
    type NodeChange,
    type NodeProps,
    type NodeTypes,
    type ReactFlowInstance,
} from 'reactflow';
import 'reactflow/dist/base.css';
import './WorkflowGraph.css';
import {BanIcon} from 'lucide-react';
import type {WorkflowDetail} from '@/types/api';
import {cn} from '@/lib/utils/cn';
import {
    buildWorkflowGraph,
    jobNodeType,
    stageNodeType,
    type JobNodeData,
    type StageNodeData,
} from './workflowGraph';
import {JobStatusDot, formatJobDuration, jobStatusKey} from './jobStatus';

const JobNode = memo(function JobNode({
    data: {job, isDependency},
    selected,
}: NodeProps<JobNodeData>) {
    const duration = formatJobDuration(job.duration);

    return (
        <>
            {job.needs?.length ? (
                <Handle
                    type="target"
                    position={Position.Left}
                    isConnectable={false}
                    className={job.disabled ? 'job-handle-disabled' : undefined}
                />
            ) : null}
            <div
                title={job.name}
                data-testid="workflow-job-node"
                className={cn(
                    'flex size-full items-center gap-2 rounded-lg border bg-card px-3 text-card-foreground shadow-xs transition-colors',
                    job.disabled
                        ? 'border-destructive/70 bg-destructive/10'
                        : 'hover:border-foreground/40',
                    selected && 'border-primary ring-2 ring-primary/30'
                )}
            >
                <JobStatusDot status={job.status} />
                <span className="min-w-0 flex-1 truncate text-sm">
                    {job.name}
                </span>
                {duration ? (
                    <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                        {duration}
                    </span>
                ) : null}
                {job.disabled && job.disabledReason ? (
                    <span title={job.disabledReason} className="shrink-0">
                        <BanIcon className="size-4 text-destructive" />
                    </span>
                ) : null}
            </div>
            {isDependency ? (
                <Handle
                    type="source"
                    position={Position.Right}
                    isConnectable={false}
                />
            ) : null}
        </>
    );
});

const StageNode = memo(function StageNode({
    data: {stage},
}: NodeProps<StageNodeData>) {
    const {t} = useTranslation();

    return (
        <div className="truncate px-1 text-xs font-semibold text-muted-foreground uppercase">
            {t('workflow.stage', 'Stage {{n}}', {n: stage})}
        </div>
    );
});

const fitViewOptions = {padding: 0.15, maxZoom: 1.25};

const nodeTypes: NodeTypes = {
    [jobNodeType]: JobNode,
    [stageNodeType]: StageNode,
};

function minimapNodeClassName(node: Node<JobNodeData | StageNodeData>) {
    return node.type === jobNodeType
        ? `minimap-${jobStatusKey((node.data as JobNodeData).job.status)}`
        : 'minimap-stage';
}

/**
 * Node graph of a workflow run (the legacy `VisualWorkflow`): a column per
 * stage, a node per job with its status and duration, and an edge from each
 * job to the jobs needing it. Selecting a job highlights its edges.
 */
export function WorkflowGraph({
    workflow,
    selectedJobId,
    onSelectJob,
}: {
    workflow: WorkflowDetail;
    selectedJobId: string | undefined;
    onSelectJob: (jobId: string | undefined) => void;
}) {
    const {nodes, edges} = useMemo(
        () => buildWorkflowGraph(workflow, selectedJobId),
        [workflow, selectedJobId]
    );

    const container = useRef<HTMLDivElement>(null);
    const [instance, setInstance] = useState<ReactFlowInstance>();
    // Until the user interacts with the graph, it is fitted to its container
    const autoFit = useRef(true);
    const stopAutoFit = () => {
        autoFit.current = false;
    };
    // Before the job panel opening resizes the container
    useLayoutEffect(() => {
        if (selectedJobId) {
            autoFit.current = false;
        }
    }, [selectedJobId]);

    // The dialog is still settling when the graph is first laid out (the runs
    // panel shows up once the run is loaded): fit the graph again whenever its
    // container is resized. Afterwards the zoom and the area shown are kept,
    // e.g. while the job panel opens, closes or is resized.
    useEffect(() => {
        const el = container.current;
        if (!instance || !el) {
            return;
        }
        let frame = 0;
        const observer = new ResizeObserver(() => {
            cancelAnimationFrame(frame);
            frame = requestAnimationFrame(() => {
                if (autoFit.current) {
                    instance.fitView(fitViewOptions);
                }
            });
        });
        observer.observe(el);

        return () => {
            cancelAnimationFrame(frame);
            observer.disconnect();
        };
    }, [instance]);

    // Nodes are controlled: only the selection is taken from the changes
    const onNodesChange = (changes: NodeChange[]) => {
        const selects = changes.filter(c => c.type === 'select');
        const selected = selects.find(c => c.selected);
        if (selected) {
            onSelectJob(selected.id);
        } else if (selects.some(c => c.id === selectedJobId)) {
            onSelectJob(undefined);
        }
    };

    return (
        <div
            ref={container}
            onPointerDownCapture={stopAutoFit}
            onWheelCapture={stopAutoFit}
            className="workflow-graph size-full bg-background"
        >
            <ReactFlow
                nodes={nodes}
                edges={edges}
                nodeTypes={nodeTypes}
                onNodesChange={onNodesChange}
                onInit={setInstance}
                fitView
                fitViewOptions={fitViewOptions}
                minZoom={0.1}
                nodesDraggable={false}
                nodesConnectable={false}
                edgesUpdatable={false}
                edgesFocusable={false}
                deleteKeyCode={null}
                selectionKeyCode={null}
                multiSelectionKeyCode={null}
            >
                <Controls showInteractive={false} />
                <MiniMap
                    pannable
                    zoomable
                    nodeClassName={minimapNodeClassName}
                    nodeBorderRadius={8}
                />
                <Background gap={12} size={1} />
            </ReactFlow>
        </div>
    );
}
