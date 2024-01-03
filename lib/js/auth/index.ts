import OAuthClient, {
    AuthEvent,
    AuthEventHandler,
    configureClientAuthentication,
    configureClientCredentialsGrantType,
    isValidSession,
    LoginEvent,
    loginEventType,
    LogoutEvent,
    logoutEventType,
    LogoutOptions,
    RefreshTokenEvent,
    refreshTokenEventType,
    sessionExpiredEventType,
} from "./src/client/OAuthClient";

import KeycloakClient from "./src/client/KeycloakClient";
import {keycloakNormalizer} from "./src/userNormalizer/keycloakNormalizer";

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
    keycloakNormalizer,
};
export type {
    AuthEvent,
    AuthEventHandler,
    LogoutEvent,
    LogoutOptions,
    LoginEvent,
    RefreshTokenEvent,
}

export * from './src/types';
