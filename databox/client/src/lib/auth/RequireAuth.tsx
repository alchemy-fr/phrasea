'use client';

import {PropsWithChildren, useEffect} from 'react';
import {useAuth} from './AuthProvider';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';

/**
 * Gate for private screens: shows a loader while the session is being
 * restored and redirects to Keycloak when the user is anonymous.
 */
export function RequireAuth({children}: PropsWithChildren) {
    const {status, login, loggingOut} = useAuth();

    useEffect(() => {
        if (status === 'anonymous' && !loggingOut) {
            login();
        }
    }, [status, loggingOut, login]);

    if (status !== 'authenticated') {
        return <FullPageLoader />;
    }

    return <>{children}</>;
}
