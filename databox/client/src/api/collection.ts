import apiClient from "./api-client";
import {Collection} from "../types";

interface CollectionOptions {
    query: string | null;
    workspaces?: string[];
}

export async function getCollections(options: CollectionOptions): Promise<Collection[]> {
    const res = await apiClient.get('/collections', {

    });

    console.log('res.data', res.data);

    return res.data;
}
