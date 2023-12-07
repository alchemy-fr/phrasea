import {createContext, MutableRefObject} from 'react';
import {AuthTokens} from "@alchemy/auth";

export type SetTokens = (tokens: AuthTokens) => void;

export type TAuthContext = {
    tokens?: AuthTokens | undefined;
    logout: (redirectPathAfterLogin?: string) => void;
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
