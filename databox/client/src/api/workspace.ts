import apiClient from "./api-client";
import {Workspace} from "../types";

export async function getWorkspace(id: string): Promise<Workspace> {
    const res = await apiClient.get(`/workspaces/${id}`);

    return res.data;
}
