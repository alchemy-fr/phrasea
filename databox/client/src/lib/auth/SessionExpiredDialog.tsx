'use client';

import {useRouter} from 'next/navigation';
import {SessionExpiredDialog as BaseSessionExpiredDialog} from '@alchemy/next-core/auth/SessionExpiredDialog';
import {useAuth} from './AuthProvider';
import {routes} from '@/lib/routes';

export function SessionExpiredDialog() {
    const {sessionExpired, login, redirecting, dismissSessionExpired} =
        useAuth();
    const router = useRouter();

    return (
        <BaseSessionExpiredDialog
            open={sessionExpired}
            redirecting={redirecting}
            onLogin={() => login()}
            onDismiss={() => {
                // Staying signed out leaves the screen the session expired
                // on — it is most likely forbidden now — for the home page
                dismissSessionExpired();
                router.push(routes.home());
            }}
        />
    );
}
