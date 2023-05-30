import apiClient from "./api-client";
import {Workflow} from "@alchemy/visual-workflow";

export async function getWorkflow(id: string): Promise<Workflow> {
    const res = await apiClient.get(`/workflows/${id}`);

    return res.data;
}

export async function rerunJob(workflowId: string, jobId: string): Promise<Workflow> {
    const res = await apiClient.post(`/workflows/${workflowId}/jobs/${jobId}/rerun`, {});

    return res.data;
}
