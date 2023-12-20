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
import AuthenticationContext, {LogoutFunction, SetTokens} from "../context/AuthenticationContext";
import SessionExpireContainer from "./SessionExpireContainer";
import {StayInFunction} from "./SessionAboutToExpireModal";

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

    const logout = useCallback<LogoutFunction>(async (redirectPathAfterLogin?: string, quiet = false) => {
        const handler = () => {
            if (redirectPathAfterLogin) {
                setRedirectPath(redirectPathAfterLogin);
            } else {
                setTimeout(() => {
                    setRedirectPath(undefined);
                }, 500);
            }
        }

        const event = await oauthClient.logout({quiet});
        console.log('event', event);
        if (event?.preventDefault) {
            handler();

            return;
        }

        handler();
    }, [setTokens, setRedirectPath]);

    const isAuthenticated = (): boolean => {
        return isValidSession(tokens);
    };

    const stayIn = React.useCallback<StayInFunction>(async () => {
        await oauthClient.getTokenFromRefreshToken();
    }, [oauthClient]);

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
        <SessionExpireContainer
            tokens={tokens}
            stayIn={stayIn}
        />
        {children}
    </AuthenticationContext.Provider>
}
