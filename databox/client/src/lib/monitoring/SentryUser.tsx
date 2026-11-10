'use client';

import {SentryUser as BaseSentryUser} from '@alchemy/next-core/monitoring/SentryUser';
import {useAuth} from '@/lib/auth/AuthProvider';

/** Attaches the signed-in user to the errors reported to Sentry */
export function SentryUser() {
    const {user, status} = useAuth();

    return <BaseSentryUser user={user} authResolved={status !== 'loading'} />;
}
