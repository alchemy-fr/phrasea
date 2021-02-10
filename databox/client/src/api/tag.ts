import apiClient from "./api-client";
import {Tag} from "../types";

type TagOptions = {
    query?: string;
    workspaceId: string;
}

export async function getTags(options: TagOptions): Promise<Tag[]> {
    const res = await apiClient.get('/tags', {
        params: {
            ...options,
        },
    });

    return res.data;
}
