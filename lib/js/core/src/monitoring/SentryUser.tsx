'use client';

import {useEffect} from 'react';
import {setSentryUser, type SentryUserInfo} from './sentry';

/**
 * Attaches the signed-in user to the errors sent to Sentry, once the session
 * is resolved (`authResolved`). Renders nothing.
 */
export function SentryUser({
    user,
    authResolved,
}: {
    user: SentryUserInfo | undefined;
    authResolved: boolean;
}) {
    const id = user?.id;
    const username = user?.username;
    const email = user?.email;

    useEffect(() => {
        if (authResolved) {
            setSentryUser(id ? {id, username, email} : undefined);
        }
    }, [authResolved, id, username, email]);

    return null;
}
