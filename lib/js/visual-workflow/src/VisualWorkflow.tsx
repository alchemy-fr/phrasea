import React from 'react';
import ReactFlow, {
    Background,
    Controls,
    Edge,
    MiniMap,
    Node,
    NodeTypes,
    useEdgesState,
    useNodesState,
} from 'reactflow';
import 'reactflow/dist/style.css';
import './style/index.scss';
import './style/VisualWorkflow.scss';
import {Job, NodeData, OnRerun, Workflow} from "./types";
import JobNode from "./Job/JobNode";
import FlowEvents from "./FlowEvents";

const nodeTypes: NodeTypes = {
    jobNode: JobNode,
};

type Props = {
    workflow: Workflow;
    nodeWith?: number;
    nodeHeight?: number;
    stageXPadding?: number;
    nodeYPadding?: number;
    onRerunJob?: OnRerun;
};

export default function VisualWorkflow({
    workflow,
    nodeWith = 300,
    nodeHeight = 55,
    stageXPadding = 30,
    nodeYPadding = 15,
    onRerunJob,
}: Props) {
    const [nodes, setNodes, onNodesChange] = useNodesState([]);
    const [edges, setEdges, onEdgesChange] = useEdgesState([]);

    React.useEffect(() => {
        const nodes: Node<NodeData>[] = [];
        const edges: Edge[] = [];

        let left = 0;
        const jobIndex: Record<string, Job> = {};

        workflow.stages.forEach((s, sIndex) => {
            let top = 0;
            s.jobs.forEach((j, jIndex) => {
                const nodeData: NodeData = {
                    ...j,
                    onRerun: onRerunJob,
                };
                jobIndex[j.id] = nodeData;

                nodes.push({
                    type: 'jobNode',
                    id: j.id,
                    position: {
                        x: stageXPadding * (1 + sIndex * 2) + nodeWith * sIndex,
                        y: nodeYPadding * (1 + jIndex * 2) + nodeHeight * jIndex,
                    },
                    data: nodeData,
                    className: `job-node ${nodeData.disabled ? 'job-node-disabled' : ''}`,
                    style: {
                        height: nodeHeight,
                        width: nodeWith,
                    },
                    selectable: true,
                    draggable: false,
                });

                j.needs?.forEach(n => {
                    jobIndex[n].isDependency = true;

                    edges.push({
                        id: `${j.id}-${n}`,
                        source: n,
                        target: j.id,
                        className: 'job-edge',
                    })
                })

                top += 100;
            });

            left += 200;
        });

        setNodes(nodes);
        setEdges(edges);
    }, [workflow]);

    return <div
        className={'visual-workflow'}
    >
        <ReactFlow
            fitView={true}
            edgesUpdatable={false}
            nodeTypes={nodeTypes}
            nodes={nodes}
            edges={edges}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChange}
        >
            <FlowEvents
                setEdges={setEdges}
            >
                <Controls/>
                <MiniMap/>
                <Background
                    gap={12}
                    size={1}
                    style={{
                        backgroundColor: '#eeeeee',
                    }}
                />
            </FlowEvents>
        </ReactFlow>
    </div>
}
