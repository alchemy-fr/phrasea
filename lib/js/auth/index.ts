import OAuthClient, {
    AuthEvent,
    AuthEventHandler,
    configureClientAuthentication,
    configureClientCredentialsGrantType,
    LoginEvent,
    loginEventType,
    LogoutEvent,
    logoutEventType,
    RefreshTokenEvent,
    refreshTokenEventType,
    sessionExpiredEventType,
    UserInfoResponse,
} from "./src/client/OAuthClient";

import {TAuthContext} from "./src/context/AuthenticationContext";
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
