'use client';

import {PropsWithChildren, useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {LockIcon} from 'lucide-react';
import {useAuth} from './AuthProvider';
import {hasLoggedOut} from './loggedOut';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {Button} from '@alchemy/next-ui/ui/button';
import {EmptyState} from '@alchemy/next-ui/ui/misc';

/**
 * Gate for private screens: shows a loader while the session is being
 * restored and redirects to Keycloak when the user is anonymous — unless
 * they just signed out, who are offered to sign in again instead.
 */
export function RequireAuth({children}: PropsWithChildren) {
    const {t} = useTranslation();
    const {status, login, redirecting, loggingOut} = useAuth();
    const [loggedOut] = useState(hasLoggedOut);

    useEffect(() => {
        if (status === 'anonymous' && !loggedOut && !loggingOut) {
            login();
        }
    }, [status, login, loggedOut, loggingOut]);

    if (status === 'anonymous' && loggedOut) {
        return (
            <EmptyState
                className="h-full"
                icon={<LockIcon />}
                title={t('auth.signed_out.title', 'You are signed out')}
                description={t(
                    'auth.signed_out.description',
                    'Sign in to access this page.'
                )}
                action={
                    <Button loading={redirecting} onClick={() => login()}>
                        {t('auth.sign_in', 'Sign in')}
                    </Button>
                }
            />
        );
    }

    if (status !== 'authenticated') {
        return <FullPageLoader />;
    }

    return <>{children}</>;
}
