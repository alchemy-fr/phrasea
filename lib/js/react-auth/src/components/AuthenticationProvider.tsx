import React, {PropsWithChildren, useCallback} from 'react';
import {
    OAuthClient,
    isValidSession,
    AuthTokens,
    logoutEventType,
    AuthEventHandler,
    LogoutEvent
} from "@alchemy/auth";
import {getSessionStorage} from "@alchemy/storage";
import AuthenticationContext, {LogoutFunction, SetTokens} from "../context/AuthenticationContext";

type Props = PropsWithChildren<{
    onNewTokens?: (tokens: AuthTokens) => void;
    onClear?: () => void;
    oauthClient: OAuthClient,
}>;

export default function AuthenticationProvider({
    oauthClient,
    children,
    onNewTokens,
}: Props) {
    const redirectPathSessionStorageKey = 'redirpath';
    const sessionStorage = getSessionStorage();
    const redirectPath = React.useRef<string | undefined>(sessionStorage.getItem(redirectPathSessionStorageKey) || undefined);
    const [tokens, setTokens] = React.useState<AuthTokens | undefined>(oauthClient.getTokens());

    React.useEffect(() => {
        const listener: AuthEventHandler<LogoutEvent> = async () => {
            setTokens(undefined);
        };

        oauthClient.registerListener(logoutEventType, listener);

        return () => {
            oauthClient.unregisterListener(logoutEventType, listener);
        }
    }, [oauthClient]);

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

    const logout = useCallback<LogoutFunction>((redirectPathAfterLogin?: string, quiet = false) => {
        if (redirectPathAfterLogin) {
            setRedirectPath(redirectPathAfterLogin);
        } else {
            setTimeout(() => {
                setRedirectPath(undefined);
            }, 500);
        }

        oauthClient.logout({quiet});
        setTokens(undefined);
    }, [setTokens, setRedirectPath]);

    const isAuthenticated = (): boolean => {
        return isValidSession(tokens);
    };

    return <AuthenticationContext.Provider
        value={{
            tokens,
            setTokens: updateTokens,
            logout,
            setRedirectPath,
            redirectPath,
            clearRedirectPath,
            isAuthenticated,
        }}
    >
        {children}
    </AuthenticationContext.Provider>
}
