import {createContext, MutableRefObject} from 'react';
import {AuthTokens} from "@alchemy/auth";

export type SetTokens = (tokens: AuthTokens) => void;

export type LogoutFunction = (redirectPathAfterLogin?: string, quiet?: boolean) => void;

export type TAuthContext = {
    tokens?: AuthTokens | undefined;
    logout: LogoutFunction;
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
