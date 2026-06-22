import {apiClient} from '../init.ts';
import {Basket} from '../types';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';
import {OperationTask, EntityName, PaginationParams} from './types.ts';

export type GetTasksOptions = {
    query?: string;
    page?: number;
} & PaginationParams;

export async function getTasks({
    nextUrl,
    ...params
}: GetTasksOptions = {}): Promise<NormalizedCollectionResponse<Basket>> {
    const res = await apiClient.get(nextUrl ?? `/${EntityName.OperationTask}`, {
        params,
    });

    return getHydraCollection(res.data);
}

export async function postRunOperationTask(
    data: Partial<OperationTask>
): Promise<OperationTask> {
    const res = await apiClient.post(`/${EntityName.OperationTask}`, data);

    return res.data;
}
