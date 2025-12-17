import AuthenticationContext, {
    TAuthContext,
    LogoutFunction,
} from './src/context/AuthenticationContext';
import AuthenticationProvider from './src/components/AuthenticationProvider';
import {useAuth} from './src/hooks/useAuth';
import {useKeycloakUrls} from './src/hooks/useKeycloakUrls';
import AuthorizationCodePage from './src/components/AuthorizationCodePage';
import {useForceLogin} from './src/hooks/useForceLogin';
import SessionAboutToExpireModal from './src/components/SessionAboutToExpireModal';
import SessionExpireContainer from './src/components/SessionExpireContainer';
import {useAuthorizationCode} from './src/hooks/useAuthorizationCode';
import {useOneTimeToken} from './src/hooks/useOneTimeToken';

export {
    AuthenticationContext,
    AuthenticationProvider,
    AuthorizationCodePage,
    useAuth,
    useKeycloakUrls,
    useForceLogin,
    useOneTimeToken,
    SessionAboutToExpireModal,
    SessionExpireContainer,
    useAuthorizationCode,
};

export type {TAuthContext, LogoutFunction};
