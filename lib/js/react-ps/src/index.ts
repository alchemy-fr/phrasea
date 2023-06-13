import IdentityProviders from "./components/IdentityProviders";
import Login from "./components/Login";
import FormLayout from "./components/FormLayout";
import DashboardMenu from "./components/DashboardMenu/DashboardMenu";
import OAuthClient, {
    authenticationEventType,
    loginEventType,
    logoutEventType,
} from "./lib/oauth-client";

export {
    IdentityProviders,
    Login,
    FormLayout,
    OAuthClient,
    authenticationEventType,
    loginEventType,
    logoutEventType,
    DashboardMenu,
};

export type {
    TokenResponse,
    UserInfoResponse,
    AuthEvent,
    LoginEvent,
    AuthenticationEvent,
    LogoutEvent,
    AuthEventHandler,
} from  "./lib/oauth-client";
