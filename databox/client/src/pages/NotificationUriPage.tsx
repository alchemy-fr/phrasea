import React from 'react';
import {getPath, useLocation, useNavigate} from '@alchemy/navigation';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import {routes} from '../routes.ts';
import {
    getNotificationTargetLocation,
    resolveNotificationUri,
} from '../hooks/useNotificationUriHandler.ts';

type Props = {};

/**
 * Entry point of the links sent in notifications (emails, …).
 *
 * Notifications only carry a client URI (`/assets/{id}`); the API wraps it into
 * `/notification-uri?uri={uri}` and this page resolves it to the actual screen.
 */
export default function NotificationUriPage({}: Props) {
    const location = useLocation();
    const navigate = useNavigate();

    React.useEffect(() => {
        const uri = new URLSearchParams(location.search).get('uri');
        const target = uri ? resolveNotificationUri(uri) : undefined;

        navigate(
            target
                ? getNotificationTargetLocation(target)
                : getPath(routes.home),
            {replace: true}
        );
    }, [location.search, navigate]);

    return <FullPageLoader />;
}
