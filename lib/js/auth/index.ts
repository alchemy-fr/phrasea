import OAuthClient, {
    configureClientAuthentication,
    configureClientCredentialsGrantType,
    isValidSession,
    loginEventType,
    logoutEventType,
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

export * from './src/types';
