import apiClient from "./api-client";
import {Collection, Workspace} from "../types";
import {ApiCollectionResponse, getHydraCollection} from "./hydra";

export const collectionChildrenLimit = 20;
export const collectionSecondLimit = 30;

type CollectionOptions = {
    limit?: number;
    childrenLimit?: number;
    page?: number;
    query?: string;
    parent?: string;
    workspaces?: string[];
    groupByWorkspace?: boolean;
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
    const collections = await getCollections({
        groupByWorkspace: true,
        limit: collectionChildrenLimit + 1,
    });

    const workspaces: {[key: string]: Workspace} = {};

    collections.result.forEach((c: Collection) => {
        if (!workspaces[c.workspace.id]) {
            workspaces[c.workspace.id] = {
                ...c.workspace,
                collections: [],
            }
        }
        const list = workspaces[c.workspace.id].collections;

        if (list.length === collectionChildrenLimit) {
            return;
        }

        list.push(c);
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

export async function addAssetToCollection(collectionIri: string, assetIri: string): Promise<Boolean> {
    const res = await apiClient.post(`/collection-assets`, {
        collection: collectionIri,
        asset: assetIri,
    });

    return res.data;
}
