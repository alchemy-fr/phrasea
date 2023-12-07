import React from "react";
import {useAuth} from "./useAuth";
import {jwtDecode} from "jwt-decode";
import {TAuthContext} from "../context/AuthenticationContext";
import {UserInfoResponse, AuthUser} from "@alchemy/auth";

export type UseUserReturn<U extends object> = {
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

export function useKeycloakUser(): UseUserReturn<AuthUser> {
    const userContext = useUser<UserInfoResponse>();

    return React.useMemo<UseUserReturn<AuthUser>>(() => {
        const {user, ...rest} = userContext;

        return {
            ...rest,
            user: user ? {
                id: user.sub,
                groups: user.groups,
                username: user.preferred_username,
                roles: user.roles,
            } as AuthUser : undefined,
        }
    }, [userContext]);
}
