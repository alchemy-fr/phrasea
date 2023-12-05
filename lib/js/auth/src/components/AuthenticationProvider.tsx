import React, {PropsWithChildren, useCallback} from 'react';
import {AuthTokens} from "../types";
import {getSessionStorage} from "@alchemy/storage";
import AuthenticationContext, {SetTokens} from "../context/AuthenticationContext";
import OAuthClient from "../client/OAuthClient";

type Props = PropsWithChildren<{
    onNewTokens?: (tokens: AuthTokens) => void;
    onClear?: () => void;
    onLogout?: () => void;
    oauthClient: OAuthClient,
}>;

export default function AuthenticationProvider({
    oauthClient,
    children,
    onNewTokens,
    onClear,
    onLogout,
}: Props) {
    const redirectPathSessionStorageKey = 'redirpath';
    const sessionStorage = getSessionStorage();
    const redirectPath = React.useRef<string | undefined>(sessionStorage.getItem(redirectPathSessionStorageKey) || undefined);
    const [tokens, setTokens] = React.useState<AuthTokens | undefined>(oauthClient.getTokens());

    const updateTokens = React.useCallback<SetTokens>((tokens) => {
        setTokens(tokens);
        onNewTokens && onNewTokens(tokens);
    }, [setTokens]);

    const setRedirectPath = React.useCallback((path: string | undefined) => {
        redirectPath.current = path;

        if (path) {
            sessionStorage.setItem(redirectPathSessionStorageKey, path);
        } else {
            sessionStorage.removeItem(redirectPathSessionStorageKey);
        }
    }, [redirectPath]);

    const clearRedirectPath = React.useCallback(() => {
        setRedirectPath(undefined);
    }, [setRedirectPath]);

    const clearSession = useCallback(() => {
        onClear && onClear();
        setTokens(undefined);
    }, [setTokens]);

    const logout = useCallback((redirectPathAfterLogin?: string) => {
        onLogout && onLogout();
        clearSession();

        if (redirectPathAfterLogin) {
            setRedirectPath(redirectPathAfterLogin);
            return;
        }

        setTimeout(() => {
            setRedirectPath(undefined);
        }, 500);
    }, [clearSession, setRedirectPath]);

    return <AuthenticationContext.Provider
        value={{
            tokens,
            setTokens: updateTokens,
            logout,
            setRedirectPath,
            redirectPath,
            clearRedirectPath,
        }}
    >
        {children}
    </AuthenticationContext.Provider>
}
