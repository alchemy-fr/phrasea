import AuthenticationContext, {TAuthContext} from "./src/context/AuthenticationContext";
import AuthenticationProvider from "./src/components/AuthenticationProvider";
import {useAuth} from "./src/hooks/useAuth";
import {useKeycloakUser, useUser, UseUserReturn} from "./src/hooks/useUser";
import {useKeycloakUrls} from "./src/hooks/useKeycloakUrls";
import AuthorizationCodePage from "./src/components/AuthorizationCodePage";
import MatomoUser from "./src/components/MatomoUser";

export {
    AuthenticationContext,
    AuthenticationProvider,
    AuthorizationCodePage,
    useAuth,
    useUser,
    useKeycloakUser,
    useKeycloakUrls,
    MatomoUser,
};
export type {
    TAuthContext,
    UseUserReturn,
}
