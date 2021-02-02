import apiClient from "./api-client";
import {Collection, Workspace} from "../types";

type CollectionOptions = {
    query?: string;
    parent?: string;
    workspaces?: string[];
}

export async function getCollections(options: CollectionOptions): Promise<Collection[]> {
    const res = await apiClient.get('/collections', {
        params: {
            ...options,
        },
    });

    return res.data;
}


export async function getWorkspaces(): Promise<Workspace[]> {
    const res = await apiClient.get('/workspaces', {});

    return res.data;
}
