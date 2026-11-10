'use client';

import {SentryInit as BaseSentryInit} from '@alchemy/next-core/monitoring/SentryInit';
import {SentryUser as BaseSentryUser} from '@alchemy/next-core/monitoring/SentryUser';
import {useConfig} from '@/lib/config/ConfigProvider';
import {useAuth} from '@/lib/auth/AuthProvider';

/** Reports the browser errors to Sentry, when the stack configures it */
export function SentryInit() {
    const {sentry, appId} = useConfig();

    return <BaseSentryInit sentry={sentry} appId={appId} appName="expose" />;
}

/** Attaches the signed-in user to the errors reported to Sentry */
export function SentryUser() {
    const {user, status} = useAuth();

    return (
        <BaseSentryUser
            user={
                user
                    ? {id: user.id, username: user.username, email: user.email}
                    : undefined
            }
            authResolved={status !== 'loading'}
        />
    );
}
