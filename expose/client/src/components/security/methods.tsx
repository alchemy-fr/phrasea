import PasswordMethod from './PasswordMethod';
import AuthenticationMethod from './AuthenticationMethod';
import {FunctionComponent} from 'react';

export type SecurityMethodProps = {
    onAuthorization: () => void;
    authorization?: string;
    securityContainerId: string;
    loading: boolean;
    error?: string;
};

export const securityMethods: Record<
    string,
    FunctionComponent<SecurityMethodProps>
> = {
    password: PasswordMethod,
    authentication: AuthenticationMethod,
};
