'use client';

import {SessionExpiredDialog as BaseSessionExpiredDialog} from '@alchemy/next-core/auth/SessionExpiredDialog';
import {useAuth} from './AuthProvider';

/** Staying signed out keeps the page: every page is public, the cards tied to roles just go away. */
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
