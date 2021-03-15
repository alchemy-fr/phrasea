import apiClient from "./api-client";
import {Collection, Workspace} from "../types";
import {ApiCollectionResponse, getHydraCollection} from "./hydra";

type CollectionOptions = {
    query?: string;
    parent?: string;
    workspaces?: string[];
}

export async function getCollections(options: CollectionOptions): Promise<ApiCollectionResponse<Collection>> {
    const res = await apiClient.get('/collections', {
        params: {
            ...options,
        },
    });

    return getHydraCollection(res.data);
}

export async function getWorkspaces(): Promise<Workspace[]> {
    const collections = await getCollections({});

    const workspaces: {[key: string]: Workspace} = {};

    collections.result.forEach((c: Collection) => {
        if (!workspaces[c.workspace.id]) {
            workspaces[c.workspace.id] = {
                ...c.workspace,
                collections: [],
            }
        }

        workspaces[c.workspace.id].collections.push(c);
    });

    return (Object.keys(workspaces) as Array<string>).map(i => workspaces[i]);
}

export async function getCollection(id: string): Promise<Collection> {
    const res = await apiClient.get(`/collections/${id}`);

    return res.data;
}

export async function patchCollection(id: string, data: Partial<Collection>): Promise<Collection> {
    const res = await apiClient.patch(`/collections/${id}`, data, {
        headers: {
            'Content-Type': 'application/merge-patch+json',
        },
    });

    return res.data;
}

type CollectionPostType = {
    parent?: string,
    title: string;
    children?: Collection[];
    workspace?: string;
    privacy: number;
}

export async function postCollection(data: CollectionPostType): Promise<Collection> {
    const res = await apiClient.post(`/collections`, data);

    return res.data;
}

export async function patchWorkspace(id: string, data: Partial<Workspace>): Promise<Workspace> {
    const res = await apiClient.patch(`/workspaces/${id}`, data, {
        headers: {
            'Content-Type': 'application/merge-patch+json',
        },
    });

    return res.data;
}
