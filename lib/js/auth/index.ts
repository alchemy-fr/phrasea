import OAuthClient, {
    AuthEvent,
    AuthEventHandler,
    configureClientAuthentication,
    configureClientCredentialsGrantType, isValidSession,
    LoginEvent,
    loginEventType,
    LogoutOptions,
    LogoutEvent,
    logoutEventType,
    RefreshTokenEvent,
    refreshTokenEventType,
    sessionExpiredEventType,
    UserInfoResponse,
} from "./src/client/OAuthClient";

import KeycloakClient from "./src/client/KeycloakClient";

export {
    OAuthClient,
    configureClientAuthentication,
    configureClientCredentialsGrantType,
    loginEventType,
    logoutEventType,
    sessionExpiredEventType,
    refreshTokenEventType,
    KeycloakClient,
    isValidSession,
};
export type {
    AuthEvent,
    AuthEventHandler,
    LogoutEvent,
    LogoutOptions,
    LoginEvent,
    RefreshTokenEvent,
    UserInfoResponse,
}

export * from './src/types';
