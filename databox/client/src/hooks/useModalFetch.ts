import {useModals} from '@alchemy/navigation';
import {useQuery} from "@tanstack/react-query";
import {useEffect} from "react";

export function useModalFetch(...args: Parameters<typeof useQuery>) {
    const {closeModal} = useModals();

    const output = useQuery(...args);

    useEffect(() => {
        if (output.isError) {
            closeModal();
        }
    }, [output.isError, closeModal]);


    return output;
}
