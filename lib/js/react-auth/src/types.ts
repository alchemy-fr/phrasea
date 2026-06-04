import {AuthTokens, AuthUser, LogoutOptions} from '@alchemy/auth';
import {RefreshTokenFunction, SetTokens} from './context/AuthenticationContext';

export type LogoutFunction = (options?: LogoutOptions) => void;

export type TAuthContext<U extends AuthUser = AuthUser> = {
    user?: U | undefined;
    tokens?: AuthTokens | undefined;
    logout: LogoutFunction;
    refreshToken?: RefreshTokenFunction;
    setTokens: SetTokens;
    isAuthenticated: boolean;
    hasSession: boolean;
};
