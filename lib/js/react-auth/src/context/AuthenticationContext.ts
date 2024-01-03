import {createContext, MutableRefObject} from 'react';
import {AuthTokens, AuthUser, LogoutOptions} from "@alchemy/auth";

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
    setRedirectPath?: ((url: string) => void) | undefined;
    clearRedirectPath: () => void;
    redirectPath?: MutableRefObject<string | undefined>; // Redirect after authentication
    isAuthenticated: () => boolean;
};

export default createContext<TAuthContext>({
    logout: () => {},
    clearRedirectPath: () => {},
    setTokens: () => {},
    isAuthenticated: () => false,
});
