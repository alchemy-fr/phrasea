import {HttpClient} from '@alchemy/api';

export type UseAppInitProps = {
    userIdProp?: string;
    apiClient: HttpClient;
};

export type QueryParams = {
    query?: string;
};

export type PaginationParams = {
    nextUrl?: string;
};

export type QueryAndPaginationParams = QueryParams & PaginationParams;
