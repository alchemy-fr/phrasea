import React, {PropsWithChildren, useCallback, useEffect} from 'react';
import {
    AuthEventHandler,
    AuthTokens,
    AuthUser,
    KeycloakClient,
    keycloakNormalizer,
    LoginEvent,
    LogoutEvent,
    OAuthClient,
    OAuthEvent,
    RefreshTokenEvent,
    UserInfoResponse,
    UserNormalizer,
} from '@alchemy/auth';
import {getSessionStorage} from '@alchemy/storage';
import AuthenticationContext, {
    LogoutFunction,
    RefreshTokenFunction,
    SetTokens,
} from '../context/AuthenticationContext';
import {jwtDecode} from 'jwt-decode';

type Props<
    U extends AuthUser,
    UIR extends UserInfoResponse,
> = PropsWithChildren<{
    onNewTokens?: (tokens: AuthTokens) => void;
    onClear?: () => void;
    oauthClient: OAuthClient<UIR>;
    keycloakClient?: KeycloakClient;
    normalizeUser?: UserNormalizer<U, UIR>;
    silentConnect?: boolean;
}>;

export default function AuthenticationProvider<
    U extends AuthUser,
    UIR extends UserInfoResponse,
>({
    oauthClient,
    children,
    onNewTokens,
    keycloakClient,
    silentConnect = true,
    // @ts-expect-error Invalid resolution
    normalizeUser = keycloakNormalizer,
}: Props<U, UIR>) {
    const redirectPathSessionStorageKey = 'redirpath';
    const sessionStorage = getSessionStorage();
    const redirectPath = React.useRef<string | undefined>(
        sessionStorage.getItem(redirectPathSessionStorageKey) || undefined
    );
    const [tokens, setTokens] = React.useState<AuthTokens | undefined>(
        oauthClient.getTokens()
    );

    const user = React.useMemo(() => {
        if (!tokens?.accessToken) {
            return;
        }

        return normalizeUser(jwtDecode<UIR>(tokens.accessToken));
    }, [tokens]);

    useEffect(() => {
        if (keycloakClient && silentConnect) {
            (async () => {
                await keycloakClient!.initKeycloakSession();
            })();
        }
    }, [silentConnect, keycloakClient]);

    React.useEffect(() => {
        const loginListener: AuthEventHandler<LoginEvent> = async event => {
            if (!event.preventDefault) {
                setTokens(event.tokens);
            }
        };

        const logoutListener: AuthEventHandler<LogoutEvent> = async event => {
            if (!event.preventDefault) {
                setTokens(undefined);
            }
        };

        const refreshTokenListener: AuthEventHandler<
            RefreshTokenEvent
        > = async event => {
            if (!event.preventDefault) {
                setTokens(event.tokens);
            }
        };

        oauthClient.registerListener(OAuthEvent.login, loginListener);
        oauthClient.registerListener(OAuthEvent.logout, logoutListener);
        oauthClient.registerListener(
            OAuthEvent.refreshToken,
            refreshTokenListener
        );

        return () => {
            oauthClient.unregisterListener(OAuthEvent.login, loginListener);
            oauthClient.unregisterListener(OAuthEvent.logout, logoutListener);
            oauthClient.unregisterListener(
                OAuthEvent.refreshToken,
                refreshTokenListener
            );
        };
    }, [oauthClient]);

    const updateTokens = React.useCallback<SetTokens>(
        tokens => {
            setTokens(tokens);
            onNewTokens && onNewTokens(tokens);
        },
        [setTokens]
    );

    const refreshToken = React.useCallback<RefreshTokenFunction>(async () => {
        return (await oauthClient.getTokenFromRefreshToken()).tokens;
    }, [oauthClient]);

    const setRedirectPath = React.useCallback(
        (path: string | undefined) => {
            redirectPath.current = path;

            if (path) {
                sessionStorage.setItem(redirectPathSessionStorageKey, path);
            } else {
                sessionStorage.removeItem(redirectPathSessionStorageKey);
            }
        },
        [redirectPath]
    );

    const clearRedirectPath = React.useCallback(() => {
        setRedirectPath(undefined);
    }, [setRedirectPath]);

    const logout = useCallback<LogoutFunction>(
        async ({redirectPathAfterLogin, ...options} = {}) => {
            const handler = () => {
                if (redirectPathAfterLogin) {
                    setRedirectPath(redirectPathAfterLogin);
                } else {
                    setTimeout(() => {
                        setRedirectPath(undefined);
                    }, 500);
                }
            };

            const event = await oauthClient.logout(options);
            if (event?.preventDefault) {
                handler();

                return;
            }

            handler();
        },
        [setTokens, setRedirectPath]
    );

    const isAuthenticated = (): boolean => {
        return oauthClient.isValidSession(tokens);
    };

    return (
        <AuthenticationContext.Provider
            value={{
                user,
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
    );
}
