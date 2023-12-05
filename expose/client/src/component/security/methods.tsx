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
    // @ts-expect-error error
    password: PasswordMethod,
    // @ts-expect-error error
    authentication: AuthenticationMethod,
};
