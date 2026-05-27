import {createContext} from 'react';
import {AuthTokens} from '@alchemy/auth';
import {TAuthContext} from '../types';

export type SetTokens = (tokens: AuthTokens) => void;
export type RefreshTokenFunction = () => Promise<AuthTokens>;

export default createContext<TAuthContext>({
    logout: () => {},
    setTokens: () => {},
    isAuthenticated: false,
    hasSession: false,
});
