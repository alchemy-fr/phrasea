import {UserInfoResponse, useUser as baseUseUser, UseUserReturn, AuthUser} from '@alchemy/auth';
import React from "react";

export function useUser(): UseUserReturn<AuthUser> {
    const userContext = baseUseUser<UserInfoResponse>();

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
