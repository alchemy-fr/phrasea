import React from 'react';
import VisualWorkflow from './VisualWorkflow';
import {OnRerun, Workflow} from './types';

type Props = {
    workflow: Workflow;
    onRerunJob: OnRerun;
};

export default function WorkflowUpdater({
    workflow: iworkflow,
    onRerunJob,
}: Props) {
    const [workflow, setWorkflow] = React.useState<Workflow>(iworkflow);

    const rerun = async (jobId: string) => {
        await onRerunJob(jobId);
        setWorkflow(p => ({
            ...p,
            stages: p.stages.map(s => ({
                ...s,
                jobs: s.jobs.map(j => ({
                    ...j,
                    name: `>${j.name}`,
                })),
            })),
        }));
    };

    return <VisualWorkflow workflow={workflow} onRerunJob={rerun} />;
}
