'use client';

import {SessionExpiredDialog as BaseSessionExpiredDialog} from '@alchemy/next-core/auth/SessionExpiredDialog';
import {useAuth} from './AuthProvider';

/** Staying signed out keeps the page: the publications are public for the most part. */
export function SessionExpiredDialog() {
    const {sessionExpired, login, redirecting, dismissSessionExpired} =
        useAuth();

    return (
        <BaseSessionExpiredDialog
            open={sessionExpired}
            redirecting={redirecting}
            onLogin={() => login()}
            onDismiss={dismissSessionExpired}
        />
    );
}
