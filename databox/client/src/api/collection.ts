import apiClient from './api-client';
import {Collection, CollectionOptionalWorkspace, Workspace} from '../types';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';
import {clearAssociationIds} from './clearAssociation';
import {useCollectionStore} from '../store/collectionStore';

import {
    EntityType,
    WorkspaceOrCollectionTreeItem,
} from '../components/Media/Collection/CollectionTree/types.ts';
import {TreeNode} from '@alchemy/phrasea-framework';

export const collectionChildrenLimit = 20;
export const collectionSecondLimit = 30;

export type CollectionOptions = {
    limit?: number;
    childrenLimit?: number;
    page?: number;
    query?: string;
    parent?: string;
    workspaces?: string[];
    nextUrl?: string;
};

export async function getCollections(
    options: CollectionOptions
): Promise<NormalizedCollectionResponse<Collection>> {
    const res = await apiClient.get(options.nextUrl ?? '/collections', {
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

export async function getCollection(id: string): Promise<Collection> {
    return (await apiClient.get(`/collections/${id}`)).data;
}

export async function putCollection(
    id: string,
    data: Partial<Collection>
): Promise<Collection> {
    const res = await apiClient.put(
        `/collections/${id}`,
        clearAssociationIds(data)
    );

    return res.data;
}

export async function moveCollection(
    id: string,
    parentId: string | undefined
): Promise<void> {
    await apiClient.put(`/collections/${id}/move/${parentId || 'root'}`, {});
    useCollectionStore.getState().moveCollection(id, parentId);
}

type CollectionPostType = {
    parent?: string;
    title: string;
    children?: CollectionOptionalWorkspace[];
    workspace?: string | undefined;
    privacy?: number;
};

export async function postCollection(
    data: CollectionPostType
): Promise<Collection> {
    const res = await apiClient.post(`/collections`, data);

    return res.data;
}

export async function putWorkspace(
    id: string,
    data: Partial<Workspace>
): Promise<Workspace> {
    const res = await apiClient.put(
        `/workspaces/${id}`,
        clearAssociationIds(data)
    );

    return res.data;
}

export async function deleteCollections(ids: string[]): Promise<void> {
    await apiClient.post(`/collections/delete-multiple`, {ids});

    useCollectionStore.getState().moveCollectionsToTrash(ids);
}

export async function restoreCollections(ids: string[]): Promise<void> {
    await apiClient.post(`/collections/restore-multiple`, {ids});

    useCollectionStore.getState().restoreCollections(ids);
}

export async function addAssetToCollection(
    collectionIri: string,
    assetIri: string
): Promise<boolean> {
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

export async function copyAssets(
    assetIds: string[],
    destIri: string,
    byReference: boolean,
    options: CopyOptions = {}
): Promise<void> {
    await apiClient.post(`/assets/copy`, {
        destination: destIri,
        ids: assetIds,
        byReference,
        ...options,
    });
}

export async function moveAssets(
    assetIds: string[],
    destIri: string
): Promise<void> {
    await apiClient.post(`/assets/move`, {
        destination: destIri,
        ids: assetIds,
    });
}

export async function createCollection(
    newCollection: TreeNode<WorkspaceOrCollectionTreeItem>
): Promise<string | undefined> {
    if (!newCollection.virtual) {
        return newCollection.data.type === EntityType.Collection
            ? `/collections/${newCollection.data.id}`
            : `/workspaces/${newCollection.data.id}`;
    }

    const createSubCollection = async (
        node: TreeNode<WorkspaceOrCollectionTreeItem>
    ): Promise<string> => {
        let parent: string | undefined;
        if (node.parentNode?.virtual) {
            parent = await createSubCollection(node.parentNode);
        } else if (node.parentNode?.data.type === EntityType.Collection) {
            parent = `/collections/${node.parentNode.data.id}`;
        }

        return (
            await postCollection({
                title: node.data.label,
                parent,
                workspace: `/workspaces/${newCollection.data.workspaceId}`,
            })
        )['@id'];
    };

    const r = await createSubCollection(newCollection);

    throw new Error('remove me');

    return r;
}
