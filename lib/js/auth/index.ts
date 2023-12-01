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

import {
    createHttpClient,
} from "./src/client/httpClient";

import AuthenticationContext, {TAuthContext} from "./src/context/AuthenticationContext";
import AuthenticationProvider from "./src/components/AuthenticationProvider";
import KeycloakClient from "./src/client/KeycloakClient";

export {
    OAuthClient,
    configureClientAuthentication,
    configureClientCredentialsGrantType,
    loginEventType,
    logoutEventType,
    sessionExpiredEventType,
    refreshTokenEventType,
    createHttpClient,
    AuthenticationContext,
    AuthenticationProvider,
    KeycloakClient,
};

export type {
    AuthEvent,
    AuthEventHandler,
    LogoutEvent,
    LoginEvent,
    TAuthContext,
    RefreshTokenEvent,
    UserInfoResponse,
}
