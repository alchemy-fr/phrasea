'use client';

import {MatomoTracker as BaseMatomoTracker} from '@alchemy/next-core/analytics/MatomoTracker';
import {useConfig} from '@/lib/config/ConfigProvider';
import {useAuth} from '@/lib/auth/AuthProvider';

/**
 * Path sent to Matomo for a page, or undefined when the page is not tracked:
 * the OAuth callback carries the authorization code, a public share URL its
 * access token.
 */
export function getTrackedPath(
    pathname: string,
    query: string
): string | undefined {
    if (pathname.startsWith('/auth/')) {
        return undefined;
    }
    const path = pathname.replace(/^(\/s\/[^/]+\/)[^/]+/, '$1_token_');

    return query ? `${path}?${query}` : path;
}

export function MatomoTracker() {
    const {user, status} = useAuth();

    return (
        <BaseMatomoTracker
            matomo={useConfig().analytics.matomo}
            userId={user?.id}
            authResolved={status !== 'loading'}
            trackedPath={getTrackedPath}
        />
    );
}
