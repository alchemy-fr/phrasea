import {AuthUser} from '../../types';
import React from 'react';

export type TUserContext = {
    user?: AuthUser | undefined;
    logout?: (redirectUri?: string | false) => void | undefined;
};

export const UserContext = React.createContext<TUserContext>({});
