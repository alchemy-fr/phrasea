import IdentityProviders from "./components/IdentityProviders";
import FormLayout from "./components/FormLayout";
import DashboardMenu from "./components/DashboardMenu/DashboardMenu";
import KeycloakClient, {
    loginEventType,
    logoutEventType,
    sessionExpiredEventType,
    refreshTokenEventType,
    RequestConfigWithAuth,
    configureClientAuthentication,
    RefreshTokenEvent,
    AuthEventHandler,
    LoginEvent,
    AuthEvent,
    LogoutEvent,
} from "./lib/oauth-client";

import {
    createHttpClient,
    RequestConfig,
} from "./lib/http-client";

import useEffectOnce from "./hooks//useEffectOnce";

export {
    IdentityProviders,
    FormLayout,
    KeycloakClient,
    RequestConfigWithAuth,
    configureClientAuthentication,
    loginEventType,
    logoutEventType,
    sessionExpiredEventType,
    refreshTokenEventType,
    DashboardMenu,
    createHttpClient,
    RequestConfig,
    useEffectOnce,
    RefreshTokenEvent,
    AuthEventHandler,
    LoginEvent,
    AuthEvent,
    LogoutEvent,
};
