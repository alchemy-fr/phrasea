import apiClient from "./api-client";
import {Tag} from "../types";
import {ApiCollectionResponse, getHydraCollection} from "./hydra";

type TagOptions = {
    query?: string;
    workspaceId: string;
}

export async function getTags(options: TagOptions): Promise<ApiCollectionResponse<Tag>> {
    const res = await apiClient.get('/tags', {
        params: {
            ...options,
        },
    });

    return getHydraCollection<Tag>(res.data);
}
