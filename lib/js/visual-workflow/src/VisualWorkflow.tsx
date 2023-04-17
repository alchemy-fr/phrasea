import React from 'react';
import ReactFlow, {Background, Controls, Edge, MiniMap} from 'reactflow';
import './style/App.scss';
import 'reactflow/dist/style.css';
import {Node} from "@reactflow/core/dist/esm/types/nodes";
import {Job, Workflow} from "./types";
import JobNode from "./JobNode";
import {NodeTypes} from "@reactflow/core/dist/esm/types/general";

const nodeTypes: NodeTypes = {
    jobNode: JobNode,
};

type Props = {
    workflow: Workflow;
    nodeWith?: number;
    nodeHeight?: number;
    stageXPadding?: number;
    nodeYPadding?: number;
};

export default function VisualWorkflow({
    workflow,
    nodeWith = 220,
    nodeHeight = 55,
    stageXPadding = 30,
    nodeYPadding = 15,
}: Props) {
    const {nodes, edges} = React.useMemo(() => {
        const nodes: Node<Job>[] = [];
        const edges: Edge[] = [];

        let left = 0;
        const jobIndex: Record<string, Job> = {};

        workflow.stages.forEach((s, sIndex) => {
            let top = 0;
            s.jobs.forEach((j, jIndex) => {
                jobIndex[j.id] = j;

                nodes.push({
                    type: 'jobNode',
                    id: j.id,
                    position: {
                        x: stageXPadding * (1 + sIndex * 2) + nodeWith * sIndex,
                        y: nodeYPadding * (1 + jIndex * 2) + nodeHeight * jIndex,
                    },
                    data: j,
                    className: 'job-node',
                    style: {
                        height: nodeHeight,
                        width: nodeWith,
                    },
                });

                j.needs?.forEach(n => {
                    jobIndex[n].isDependency = true;

                    edges.push({
                        id: `${j.id}-${n}`,
                        source: n,
                        target: j.id,
                    })
                })

                top += 100;
            });

            left += 200;
        });

        return {nodes, edges};
    }, [workflow]);

    return <div style={{width: '100%', height: '100%'}}>
        <ReactFlow
            fitView={true}
            edgesUpdatable={false}
            nodeTypes={nodeTypes}
            nodes={nodes}
            edges={edges}
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
        </ReactFlow>
    </div>
}
