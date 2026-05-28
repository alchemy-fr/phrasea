import React from 'react';
import AuthenticationContext from '../context/AuthenticationContext';
import {AuthUser} from '@alchemy/auth';
import {TAuthContext} from '../types';

export function useAuth<U extends AuthUser>(): TAuthContext<U> {
    return React.useContext(AuthenticationContext) as TAuthContext<U>;
}
