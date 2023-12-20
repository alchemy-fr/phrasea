import React, {PropsWithChildren, useCallback} from 'react';
import {
    AuthEventHandler,
    AuthTokens,
    isValidSession,
    LogoutEvent,
    logoutEventType,
    OAuthClient,
    RefreshTokenEvent, refreshTokenEventType
} from "@alchemy/auth";
import {getSessionStorage} from "@alchemy/storage";
import AuthenticationContext, {LogoutFunction, RefreshTokenFunction, SetTokens} from "../context/AuthenticationContext";

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
        const logoutListener: AuthEventHandler<LogoutEvent> = async (event) => {
            if (!event.preventDefault) {
                setTokens(undefined);
            }
        };

        const refreshTokenListener: AuthEventHandler<RefreshTokenEvent> = async (event) => {
            if (!event.preventDefault) {
                setTokens(event.tokens);
            }
        };

        oauthClient.registerListener(logoutEventType, logoutListener);
        oauthClient.registerListener(refreshTokenEventType, refreshTokenListener);

        return () => {
            oauthClient.unregisterListener(logoutEventType, logoutListener);
            oauthClient.unregisterListener(refreshTokenEventType, refreshTokenListener);
        }
    }, [oauthClient]);

    const updateTokens = React.useCallback<SetTokens>((tokens) => {
        setTokens(tokens);
        onNewTokens && onNewTokens(tokens);
    }, [setTokens]);

    const refreshToken = React.useCallback<RefreshTokenFunction>(async () => {
        return await oauthClient.getTokenFromRefreshToken();
    }, [oauthClient]);

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

    const logout = useCallback<LogoutFunction>(async ({
        redirectPathAfterLogin,
        ...options
    } = {}) => {
        const handler = () => {
            if (redirectPathAfterLogin) {
                setRedirectPath(redirectPathAfterLogin);
            } else {
                setTimeout(() => {
                    setRedirectPath(undefined);
                }, 500);
            }
        }

        const event = await oauthClient.logout(options);
        if (event?.preventDefault) {
            handler();

            return;
        }

        handler();
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
            refreshToken: tokens ? refreshToken : undefined,
        }}
    >
        {children}
    </AuthenticationContext.Provider>
}
