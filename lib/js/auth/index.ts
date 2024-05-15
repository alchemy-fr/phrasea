import OAuthClient, {
    configureClientAuthentication,
    configureClientCredentialsGrantType, inIframe,
    isValidSession,
    loginEventType,
    logoutEventType,
    refreshTokenEventType,
    sessionExpiredEventType,
} from "./src/client/OAuthClient";

import KeycloakClient from "./src/client/KeycloakClient";
import {keycloakNormalizer} from "./src/userNormalizer/keycloakNormalizer";
import {openLoginWindow} from "./src/openLoginWindow";

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
    inIframe,
    openLoginWindow,
};

export * from './src/types';
