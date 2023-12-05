import PasswordMethod from './PasswordMethod';
import AuthenticationMethod from './AuthenticationMethod';
import {FunctionComponent} from 'react';

export const securityMethods: Record<
    string,
    FunctionComponent<{
        onAuthorization: () => void;
        authorization?: string;
        securityContainerId: string;
        error?: string;
    }>
> = {
    // @ts-ignore
    password: PasswordMethod,
    // @ts-ignore
    authentication: AuthenticationMethod,
};
