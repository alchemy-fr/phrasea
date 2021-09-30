import apiClient from "./api-client";
import {Tag} from "../types";
import {ApiCollectionResponse, getHydraCollection} from "./hydra";

type TagOptions = {
    query?: string;
    workspace: string;
}

export async function getTags(options: TagOptions): Promise<ApiCollectionResponse<Tag>> {
    const res = await apiClient.get('/tags', {
        params: {
            ...options,
        },
    });

    return getHydraCollection<Tag>(res.data);
}

export async function postTag({name, workspaceId}: {
    name: string;
    workspaceId: string;
}): Promise<Tag> {
    const res = await apiClient.post('/tags', {
        name,
        workspace: workspaceId,
    });

    return res.data;
}

export async function deleteTag(id: string): Promise<void> {
    await apiClient.delete(`/tags/${id}`);
}
