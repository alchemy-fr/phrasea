import {createContext} from 'react';
import {AuthTokens, AuthUser, LogoutOptions} from '@alchemy/auth';

export type SetTokens = (tokens: AuthTokens) => void;
export type RefreshTokenFunction = () => Promise<AuthTokens>;

type ExtendedLogoutOptions = {
    redirectPathAfterLogin?: string;
} & LogoutOptions;

export type LogoutFunction = (options?: ExtendedLogoutOptions) => void;

export type TAuthContext<U extends AuthUser = AuthUser> = {
    user?: U | undefined;
    tokens?: AuthTokens | undefined;
    logout: LogoutFunction;
    refreshToken?: RefreshTokenFunction;
    setTokens: SetTokens;
    isAuthenticated: boolean;
    hasSession: boolean;
};

export default createContext<TAuthContext>({
    logout: () => {},
    setTokens: () => {},
    isAuthenticated: false,
    hasSession: false,
});
