import OAuthClient, {
    loginEventType,
    logoutEventType,
    sessionExpiredEventType,
    refreshTokenEventType,
    configureClientAuthentication,
    configureClientCredentialsGrantType,
    RefreshTokenEvent,
    UserInfoResponse,
    AuthEventHandler,
    LoginEvent,
    AuthEvent,
    LogoutEvent,
} from "./src/client/OAuthClient";

import AuthenticationContext, {TAuthContext} from "./src/context/AuthenticationContext";
import AuthenticationProvider from "./src/components/AuthenticationProvider";
import KeycloakClient from "./src/client/KeycloakClient";
import {useAuth} from "./src/hooks/useAuth";
import {useUser, UseUserReturn} from "./src/hooks/useUser";
export {
    OAuthClient,
    configureClientAuthentication,
    configureClientCredentialsGrantType,
    loginEventType,
    logoutEventType,
    sessionExpiredEventType,
    refreshTokenEventType,
    AuthenticationContext,
    AuthenticationProvider,
    KeycloakClient,
    useAuth,
    useUser,
};
export type {
    AuthEvent,
    AuthEventHandler,
    LogoutEvent,
    LoginEvent,
    TAuthContext,
    RefreshTokenEvent,
    UserInfoResponse,
    UseUserReturn,
}

export * from './src/types';
