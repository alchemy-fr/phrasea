import AuthenticationContext, {TAuthContext, LogoutFunction} from "./src/context/AuthenticationContext";
import AuthenticationProvider from "./src/components/AuthenticationProvider";
import {useAuth} from "./src/hooks/useAuth";
import {useKeycloakUrls} from "./src/hooks/useKeycloakUrls";
import AuthorizationCodePage from "./src/components/AuthorizationCodePage";
import MatomoUser from "./src/components/MatomoUser";
import {useForceLogin} from "./src/hooks/useForceLogin";
import SessionAboutToExpireModal from "./src/components/SessionAboutToExpireModal";
import SessionExpireContainer from "./src/components/SessionExpireContainer";
import {useAuthorizationCode} from "./src/hooks/useAuthorizationCode";

export {
    AuthenticationContext,
    AuthenticationProvider,
    AuthorizationCodePage,
    useAuth,
    useKeycloakUrls,
    MatomoUser,
    useForceLogin,
    SessionAboutToExpireModal,
    SessionExpireContainer,
    useAuthorizationCode,
};
export type {
    TAuthContext,
    LogoutFunction,
}
