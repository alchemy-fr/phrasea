'use client';

import {PropsWithChildren, useEffect} from 'react';
import {useSearchParams} from 'next/navigation';
import {useTranslation} from 'react-i18next';
import {LogInIcon} from 'lucide-react';
import {LOGGED_OUT_PARAM, useAuth} from './AuthProvider';
import {FullPageLoader} from '@alchemy/next-ui/ui/loader';
import {Button} from '@alchemy/next-ui/ui/button';

/**
 * Gate for private screens: shows a loader while the session is being
 * restored and redirects to Keycloak when the user is anonymous — unless they
 * just signed out, in which case they are asked to sign in again.
 */
export function RequireAuth({children}: PropsWithChildren) {
    const {t} = useTranslation();
    const {status, login, redirecting} = useAuth();
    const loggedOut = useSearchParams().has(LOGGED_OUT_PARAM);

    useEffect(() => {
        if (status === 'anonymous' && !loggedOut) {
            login();
        }
    }, [status, loggedOut, login]);

    if (status === 'anonymous' && loggedOut) {
        return (
            <div
                data-testid="logged-out"
                className="flex h-full flex-col items-center justify-center gap-4 p-8 text-center"
            >
                <p className="text-muted-foreground">
                    {t(
                        'auth.logged_out',
                        'Please sign in to access this page.'
                    )}
                </p>
                <Button loading={redirecting} onClick={() => login('/')}>
                    <LogInIcon /> {t('auth.sign_in', 'Sign in')}
                </Button>
            </div>
        );
    }

    if (status !== 'authenticated') {
        return <FullPageLoader />;
    }

    return <>{children}</>;
}
