import AuthenticationContext, {TAuthContext, LogoutFunction} from "./src/context/AuthenticationContext";
import AuthenticationProvider from "./src/components/AuthenticationProvider";
import {useAuth} from "./src/hooks/useAuth";
import {useKeycloakUser, useUser, UseUserReturn} from "./src/hooks/useUser";
import {useKeycloakUrls} from "./src/hooks/useKeycloakUrls";
import AuthorizationCodePage from "./src/components/AuthorizationCodePage";
import MatomoUser from "./src/components/MatomoUser";
import {useForceLogin} from "./src/hooks/useForceLogin";

export {
    AuthenticationContext,
    AuthenticationProvider,
    AuthorizationCodePage,
    useAuth,
    useUser,
    useKeycloakUser,
    useKeycloakUrls,
    MatomoUser,
    useForceLogin,
};
export type {
    TAuthContext,
    UseUserReturn,
    LogoutFunction,
}
