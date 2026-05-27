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
            onNewTokens?.(tokens);
        },
        [setTokens]
    );

    const refreshToken = React.useCallback<RefreshTokenFunction>(async () => {
        return (await oauthClient.getTokenFromRefreshToken()).tokens;
    }, [oauthClient]);

    const logout = useCallback<LogoutFunction>(async options => {
        await oauthClient.logout(options);
    }, []);

    return (
        <AuthenticationContext.Provider
            value={{
                user,
                tokens,
                setTokens: updateTokens,
                logout,
                isAuthenticated: oauthClient.isAccessTokenValid(),
                hasSession: oauthClient.hasSession(),
                refreshToken: tokens ? refreshToken : undefined,
            }}
        >
            {children}
        </AuthenticationContext.Provider>
    );
}
