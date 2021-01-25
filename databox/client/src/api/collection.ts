import apiClient from "./api-client";
import {Collection} from "../types";

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

    console.log('res.data', res.data);

    return res.data;
}
