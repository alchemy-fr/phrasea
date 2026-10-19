'use client';

import {useEffect, useRef, useState} from 'react';
import {usePathname, useSearchParams} from 'next/navigation';
import {useConfig} from '@/lib/config/ConfigProvider';
import {useAuth} from '@/lib/auth/AuthProvider';
import {initMatomo, setUserId, trackPageView} from './matomo';

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

/**
 * Sends the page views and the user id to Matomo when the stack configures
 * it. Renders nothing.
 *
 * Next navigates without reloading the page: the URL, the referrer and the
 * title are given to Matomo before every page view.
 */
export function MatomoTracker() {
    const matomo = useConfig().analytics.matomo;
    const {user, status} = useAuth();
    const pathname = usePathname();
    const query = useSearchParams().toString();
    const history = useRef<{url?: string; referrer?: string}>({});
    const [ready] = useState(() => {
        if (matomo) {
            initMatomo(matomo);
        }

        return Boolean(matomo);
    });
    const userId = user?.id;
    // Page views wait for the user id, known once the session is restored
    const authResolved = status !== 'loading';

    useEffect(() => {
        if (ready && authResolved) {
            setUserId(userId);
        }
    }, [ready, authResolved, userId]);

    useEffect(() => {
        if (!ready || !authResolved) {
            return;
        }
        const path = getTrackedPath(pathname, query);
        if (!path) {
            return;
        }
        const url = `${window.location.origin}${path}`;
        // Effects may run twice for the same URL (strict mode)
        if (history.current.url !== url) {
            history.current = {url, referrer: history.current.url};
        }
        const {referrer} = history.current;

        // Next updates the document title after the navigation has rendered
        const timeout = setTimeout(() => {
            trackPageView(url, document.title, referrer);
        }, 0);

        return () => clearTimeout(timeout);
    }, [ready, authResolved, pathname, query]);

    return null;
}
