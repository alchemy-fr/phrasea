import {useModals} from '@alchemy/navigation';
import {useQuery} from "@tanstack/react-query";
import {useEffect} from "react";
import type {DefaultError, QueryKey} from "@tanstack/query-core";

export function useModalFetch<
    TQueryFnData = unknown,
    TError = DefaultError,
    TData = TQueryFnData,
    TQueryKey extends QueryKey = QueryKey,
>(...args: Parameters<typeof useQuery>) {
    const {closeModal} = useModals();

    const output = useQuery<
        TQueryFnData,
        TError,
        TData,
        TQueryKey
>(...args);

    useEffect(() => {
        if (output.isError) {
            closeModal();
        }
    }, [output.isError, closeModal]);


    return output;
}
