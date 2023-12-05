import React, {PropsWithChildren, useCallback} from 'react';
import {AuthTokens} from "../types";
import {CookieStorage, getSessionStorage, IStorage} from "@alchemy/storage";
import AuthenticationContext, {SetTokens} from "../context/AuthenticationContext";

type Props = PropsWithChildren<{
    onNewTokens?: (tokens: AuthTokens) => void;
    onClear?: () => void;
    onLogout?: () => void;
    storage?: IStorage;
    storageKey?: string;
}>;

export default function AuthenticationProvider({
    children,
    onNewTokens,
    onClear,
    onLogout,
    storage: customStorage,
    storageKey = 'auth',
}: Props) {
    const redirectPathSessionStorageKey = 'redirpath';
    const storage = customStorage ?? new CookieStorage({
        fallback: true,
    });
    const sessionStorage = getSessionStorage();
    const inStorage = React.useMemo<AuthTokens | undefined>(() => {
        const tokens  = storage.getItem(storageKey);
        if (tokens) {
            try {
                return JSON.parse(tokens);
            } catch (e: any) {
            }
        }
    }, []);

    const redirectPath = React.useRef<string | undefined>(sessionStorage.getItem(redirectPathSessionStorageKey) || undefined);
    const [tokens, setTokens] = React.useState<AuthTokens | undefined>(inStorage);

    const updateTokens = React.useCallback<SetTokens>((tokens) => {
        setTokens(tokens);
        storage.setItem(storageKey, JSON.stringify(tokens));
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
