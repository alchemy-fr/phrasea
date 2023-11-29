import {AuthUser} from '../../types';

export type TUserContext = {
    user?: AuthUser | undefined;
    logout?: (redirectUri?: string | false) => void | undefined;
};

export const UserContext = React.createContext<TUserContext>({});
