import apiClient from "./api-client";
import {Workflow} from "@alchemy/visual-workflow";

export async function getWorkflow(id: string): Promise<Workflow> {
    const res = await apiClient.get(`/workflows/${id}`);

    return res.data;
}
