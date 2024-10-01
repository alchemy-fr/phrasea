import {useModals} from '@alchemy/navigation';
import {
    DefinedInitialDataOptions,
    DefinedUseQueryResult,
    UndefinedInitialDataOptions,
    useQuery,
    UseQueryOptions,
    UseQueryResult,
} from '@tanstack/react-query';
import {useEffect} from 'react';
import type {DefaultError, QueryClient, QueryKey} from '@tanstack/query-core';

export function useModalFetch<
    TQueryFnData = unknown,
    TError = DefaultError,
    TData = TQueryFnData,
    TQueryKey extends QueryKey = QueryKey,
>(
    options: DefinedInitialDataOptions<TQueryFnData, TError, TData, TQueryKey>,
    queryClient?: QueryClient
): DefinedUseQueryResult<TData, TError>;

export function useModalFetch<
    TQueryFnData = unknown,
    TError = DefaultError,
    TData = TQueryFnData,
    TQueryKey extends QueryKey = QueryKey,
>(
    options: UndefinedInitialDataOptions<
        TQueryFnData,
        TError,
        TData,
        TQueryKey
    >,
    queryClient?: QueryClient
): UseQueryResult<TData, TError>;

export function useModalFetch<
    TQueryFnData = unknown,
    TError = DefaultError,
    TData = TQueryFnData,
    TQueryKey extends QueryKey = QueryKey,
>(
    options: UseQueryOptions<TQueryFnData, TError, TData, TQueryKey>,
    queryClient?: QueryClient
): UseQueryResult<TData, TError>;

export function useModalFetch(options: any, queryClient?: QueryClient): any {
    const {closeModal} = useModals();

    const output = useQuery(
        {
            retry: false,
            ...options,
        },
        queryClient
    );

    useEffect(() => {
        if (output.isError) {
            closeModal();
        }
    }, [output.isError, closeModal]);

    return output;
}
