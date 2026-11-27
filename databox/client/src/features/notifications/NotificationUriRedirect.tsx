'use client';

import {useEffect} from 'react';
import {FullPageLoader} from '@alchemy/ui/ui/loader';
import {notificationTarget} from './notificationUri';

/**
 * Leaves `/notification-uri` with a full page load, never a client-side
 * navigation: the screen opened above the page would otherwise take
 * `/notification-uri` as the place to go back to on close.
 */
export function NotificationUriRedirect() {
    useEffect(() => {
        window.location.replace(
            notificationTarget(new URLSearchParams(window.location.search))
        );
    }, []);

    return <FullPageLoader />;
}
