import apiClient from "./api-client";
import {Collection, CollectionOptionalWorkspace, Workspace} from "../types";
import {ApiCollectionResponse, getHydraCollection} from "./hydra";
import {clearAssociationIds} from "./clearAssociation";

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

const cache: Record<string, any> = {};

export function clearWorkspaceCache(): void {
    delete cache.ws;
}

export async function getWorkspaces(): Promise<Workspace[]> {
    if (cache.hasOwnProperty('ws')) {
        return cache.ws;
    }

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

    return cache.ws = (Object.keys(workspaces) as Array<string>).map(i => workspaces[i]);
}

export async function getCollection(id: string): Promise<Collection> {
    const res = await apiClient.get(`/collections/${id}`);

    return res.data;
}

export async function putCollection(id: string, data: Partial<Collection>): Promise<Collection> {
    const res = await apiClient.put(`/collections/${id}`, clearAssociationIds(data));

    return res.data;
}

export async function moveCollection(id: string, parentId: string | undefined): Promise<void> {
    await apiClient.put(`/collections/${id}/move/${parentId ? parentId : 'root'}`);
}

type CollectionPostType = {
    parent?: string,
    title: string;
    children?: CollectionOptionalWorkspace[];
    workspace?: string | undefined;
    privacy: number;
}

export async function postCollection(data: CollectionPostType): Promise<Collection> {
    const res = await apiClient.post(`/collections`, data);

    return res.data;
}

export async function putWorkspace(id: string, data: Partial<Workspace>): Promise<Workspace> {
    const res = await apiClient.put(`/workspaces/${id}`, clearAssociationIds(data));

    return res.data;
}

export async function deleteCollection(id: string): Promise<void> {
    await apiClient.delete(`/collections/${id}`);
}

export async function addAssetToCollection(collectionIri: string, assetIri: string): Promise<Boolean> {
    const res = await apiClient.post(`/collection-assets`, {
        collection: collectionIri,
        asset: assetIri,
    });

    return res.data;
}

type CopyOptions = {
    withAttributes?: boolean;
    withTags?: boolean;
};

export async function copyAssets(assetIds: string[], destIri: string, byReference: boolean, options: CopyOptions = {}): Promise<void> {
    await apiClient.post(`/assets/copy`, {
        destination: destIri,
        ids: assetIds,
        byReference,
        ...options,
    });
}

export async function moveAssets(assetIds: string[], destIri: string): Promise<void> {
    await apiClient.post(`/assets/move`, {
        destination: destIri,
        ids: assetIds,
    });
}
