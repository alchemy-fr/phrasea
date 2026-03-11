import {apiClient} from '../init.ts';
import {Page} from '../types';
import {getHydraCollection, NormalizedCollectionResponse} from '@alchemy/api';
import {EntityName} from './types.ts';
import {AxiosRequestConfig} from 'axios';

export type GetPageOptions = {
    query?: string;
    page?: number;
};

export async function getPages(
    nextUrl?: string | undefined,
    params: GetPageOptions = {}
): Promise<NormalizedCollectionResponse<Page>> {
    const res = await apiClient.get(nextUrl ?? `/${EntityName.Page}`, {
        params,
    });

    return getHydraCollection(res.data);
}

export async function putPage(id: string, data: Partial<Page>): Promise<Page> {
    const res = await apiClient.put(`/${EntityName.Page}/${id}`, data);

    return res.data;
}

export async function postPage(data: Partial<Page>): Promise<Page> {
    const res = await apiClient.post(`/${EntityName.Page}`, data);

    return res.data;
}

export async function getPage(id: string): Promise<Page> {
    return (await apiClient.get(`/${EntityName.Page}/${id}`)).data;
}

export async function getPageBySlug(
    slug: string,
    config?: AxiosRequestConfig
): Promise<Page> {
    return (await apiClient.get(`/page-by-slug/${slug}`, config)).data;
}

export async function deletePage(id: string): Promise<void> {
    await apiClient.delete(`/${EntityName.Page}/${id}`);
}
