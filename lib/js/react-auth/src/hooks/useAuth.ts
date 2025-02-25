import React from 'react';
import AuthenticationContext, {
    TAuthContext,
} from '../context/AuthenticationContext';
import {AuthUser} from '@alchemy/auth';

export function useAuth<U extends AuthUser>(): TAuthContext<U> {
    return React.useContext(AuthenticationContext) as TAuthContext<U>;
}
