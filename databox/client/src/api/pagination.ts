import {StateSetter} from "../types.ts";
import {ApiCollectionResponse} from "./hydra.ts";
import {LoadMoreFunc} from "../components/AssetList/types.ts";

export type Pagination<T> = {
    loading: boolean;
    loadingMore: boolean;
    total?: number;
    next?: string | null;
    pages: T[][];
}

export function createDefaultPagination<T>(): Pagination<T> {
    return {
        loading: true,
        loadingMore: false,
        pages: [],
    };
}

type Loader<T> = (next?: string | undefined) => Promise<ApiCollectionResponse<T>>;
type PaginatedLoader<T> = (next?: string) => Promise<ApiCollectionResponse<T>>;

export function createLoadMore<T>(
    paginatedLoader: PaginatedLoader<T>,
    pagination: Pagination<T>
): LoadMoreFunc | undefined {
    if (pagination.next) {
        return async () => {
            return await paginatedLoader(pagination.next!);
        };
    }
}

export function createPaginatedLoader<T>(
    loader: Loader<T>,
    setter: StateSetter<Pagination<T>>
): PaginatedLoader<T> {
    return async (next?: string): Promise<ApiCollectionResponse<T>> => {
        if (next) {
            setter(p => ({
                ...p,
                loadingMore: true,
            }));
        } else {
            setter(p => ({
                ...p,
                loading: true,
            }));
        }

        try {
            const r = await loader(next);
            setter(p => ({
                total: r.total,
                pages: next ? p!.pages.concat([r.result]) : [r.result],
                next: r.next,
                loadingMore: false,
                loading: false,
            }));

            return r;
        } catch (e: any) {
            setter(p => ({
                ...p,
                loadingMore: false,
                loading: false,
            }));

            throw e;
        }
    }
}
