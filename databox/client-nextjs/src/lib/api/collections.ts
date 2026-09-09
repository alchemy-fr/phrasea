import {api} from './http';
import {toPage} from './hydra';
import {
    Collection,
    CollectionPrivacyInfo,
    EntityName,
    HydraCollection,
    Page,
    Workspace,
} from '@/types/api';
import {toIris} from '@/lib/utils/iri';

export const collectionChildrenLimit = 20;
export const collectionPageLimit = 30;

export type CollectionListOptions = {
    url?: string;
    limit?: number;
    childrenLimit?: number;
    page?: number;
    query?: string;
    parent?: string;
    workspaces?: string[];
};

export async function getCollections({
    url,
    ...params
}: CollectionListOptions): Promise<Page<Collection>> {
    return toPage(
        await api.get<HydraCollection<Collection>>(
            url ?? `/${EntityName.Collection}`,
            {
                params: url ? undefined : params,
            }
        )
    );
}

export function getCollection(id: string): Promise<Collection> {
    return api.get<Collection>(`/${EntityName.Collection}/${id}`);
}

export function getCollectionAscendants(id: string): Promise<Collection> {
    return api.get<Collection>(`/${EntityName.Collection}/${id}/ascendants`);
}

export function getCollectionPrivacyInfo(
    id: string
): Promise<CollectionPrivacyInfo> {
    return api.get<CollectionPrivacyInfo>(
        `/${EntityName.Collection}/${id}/privacy`
    );
}

export type CollectionInput = {
    name?: string;
    parent?: string;
    workspace?: string;
    privacy?: number;
    translations?: Record<string, Record<string, string>>;
};

export function putCollection(
    id: string,
    data: CollectionInput
): Promise<Collection> {
    return api.put<Collection>(`/${EntityName.Collection}/${id}`, toIris(data));
}

export function postCollection(data: CollectionInput): Promise<Collection> {
    return api.post<Collection>(`/${EntityName.Collection}`, data);
}

export function moveCollection(
    id: string,
    parentId: string | undefined
): Promise<void> {
    return api.put(
        `/${EntityName.Collection}/${id}/move/${parentId ?? 'root'}`,
        {}
    );
}

export function deleteCollections(ids: string[]): Promise<void> {
    return api.post(`/${EntityName.Collection}/delete-multiple`, {ids});
}

export function restoreCollections(ids: string[]): Promise<void> {
    return api.post(`/${EntityName.Collection}/restore-multiple`, {ids});
}

export function addAssetToCollection(
    collectionIri: string,
    assetIri: string
): Promise<void> {
    return api.post('/collection-assets', {
        collection: collectionIri,
        asset: assetIri,
    });
}

// Workspaces --------------------------------------------------------------

export async function getWorkspaces(url?: string): Promise<Page<Workspace>> {
    return toPage(
        await api.get<HydraCollection<Workspace>>(
            url ?? `/${EntityName.Workspace}`,
            {
                params: url ? undefined : {limit: 100},
            }
        )
    );
}

export function getWorkspace(id: string): Promise<Workspace> {
    return api.get<Workspace>(`/${EntityName.Workspace}/${id}`);
}

export function putWorkspace(
    id: string,
    data: Partial<Workspace>
): Promise<Workspace> {
    return api.put<Workspace>(`/${EntityName.Workspace}/${id}`, toIris(data));
}
