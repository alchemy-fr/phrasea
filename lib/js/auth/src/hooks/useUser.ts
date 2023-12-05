import React from "react";
import {useAuth} from "./useAuth";
import {jwtDecode} from "jwt-decode";
import {TAuthContext} from "../context/AuthenticationContext";

type UseUserReturn<U extends object> = {
    user: U | undefined
} & TAuthContext;

export function useUser<U extends object>(): UseUserReturn<U> {
    const usedAuth = useAuth();

    const {tokens} = usedAuth;

    const user = React.useMemo(() => {
        if (!tokens?.accessToken) {
            return;
        }

        return jwtDecode<U>(tokens.accessToken);
    }, [tokens]);

    return {
        ...usedAuth,
        user,
    };
}
