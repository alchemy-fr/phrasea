import apiClient from './api-client';
import {Workflow} from '@alchemy/visual-workflow';

export async function getWorkflows(assetId: string): Promise<Workflow[]> {
    const res = await apiClient.get(`/workflows`, {
        params: {
            asset: `/assets/${assetId}`,
        },
    });

    return res.data['hydra:member'];
}

export async function getWorkflow(id: string): Promise<Workflow> {
    const res = await apiClient.get(`/workflows/${id}`);

    return res.data;
}

export async function rerunJob(
    workflowId: string,
    jobId: string
): Promise<Workflow> {
    const res = await apiClient.post(
        `/workflows/${workflowId}/jobs/${jobId}/rerun`,
        {}
    );

    return res.data;
}


export async function cancelWorkflow(
    workflowId: string,
): Promise<Workflow> {
    const res = await apiClient.post(
        `/workflows/${workflowId}/cancel`,
        {}
    );

    return res.data;
}
