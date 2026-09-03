import {apiClient} from '../init.ts';
import {EntityList} from '../types';
import {
    NormalizedCollectionResponse,
    getHydraCollection,
    createIriFromId,
} from '@alchemy/api';
import {SortWay} from './common.ts';
import {EntityName} from './types.ts';
import {QueryAndPaginationParams} from '@alchemy/phrasea-framework';
import {downloadUrl} from '@alchemy/core';

export const entityTypeNS = EntityName.EntityList;

type EntityListOptions = {
    workspace?: string;
    workspaceId: string;
} & QueryAndPaginationParams;

export async function getEntityLists({
    workspaceId,
    nextUrl,
    query,
    ...options
}: EntityListOptions): Promise<NormalizedCollectionResponse<EntityList>> {
    const res = await apiClient.get(nextUrl ?? EntityName.EntityList, {
        params: {
            ...(options ?? {}),
            ...(query
                ? {
                      name: query,
                  }
                : {}),
            workspace: workspaceId,
            [`order[name]`]: SortWay.ASC,
        },
    });

    return getHydraCollection<EntityList>(res.data);
}

export async function postEntityList(
    workspaceId: string,
    data: Partial<EntityList>
): Promise<EntityList> {
    const res = await apiClient.post(EntityName.EntityList, {
        ...data,
        workspace: createIriFromId(EntityName.Workspace, workspaceId),
    });

    return res.data;
}

export async function putEntityList(
    id: string,
    data: Partial<EntityList>
): Promise<EntityList> {
    const res = await apiClient.patch(`${EntityName.EntityList}/${id}`, data);

    return res.data;
}

export async function deleteEntityList(id: string): Promise<void> {
    await apiClient.delete(`${EntityName.EntityList}/${id}`);
}

export async function exportEntities(
    listId: string,
    options: {
        format: string;
        locale?: string;
    }
): Promise<void> {
    const response = await apiClient.post(
        `${EntityName.EntityList}/${listId}/export`,
        options,
        {
            responseType: 'blob',
        }
    );

    const disposition = response.headers['content-disposition'];

    const filename =
        disposition?.match(/filename="?([^"]+)"?/)?.[1] ?? 'download';

    const url = URL.createObjectURL(response.data);

    downloadUrl(url, filename, true);
}

export async function importEntities(
    listId: string,
    format: string,
    data: string
): Promise<void> {
    await apiClient.post(`${EntityName.EntityList}/${listId}/import`, {
        format,
        data,
    });
}

export async function clearEntityList(listId: string): Promise<void> {
    await apiClient.post(`${EntityName.EntityList}/${listId}/clear`, {});
}
