'use client';

import {useEffect, useRef, useState} from 'react';
import {usePathname, useSearchParams} from 'next/navigation';
import {
    initMatomo,
    setUserId,
    trackPageView,
    type MatomoConfig,
} from './matomo';

/**
 * Path sent to Matomo for a page, or undefined when the page is not tracked:
 * the OAuth callback carries the authorization code.
 */
export function getTrackedPath(
    pathname: string,
    query: string
): string | undefined {
    if (pathname.startsWith('/auth/')) {
        return undefined;
    }

    return query ? `${pathname}?${query}` : pathname;
}

/**
 * Sends the page views and the user id to Matomo when the stack configures
 * it (`matomo`). Renders nothing.
 *
 * Next navigates without reloading the page: the URL, the referrer and the
 * title are given to Matomo before every page view. Page views wait for the
 * session to be resolved (`authResolved`), to come with the user id.
 */
export function MatomoTracker({
    matomo,
    userId,
    authResolved,
    trackedPath = getTrackedPath,
}: {
    matomo: MatomoConfig | undefined;
    userId: string | undefined;
    authResolved: boolean;
    /** What is sent of a URL, `getTrackedPath` by default */
    trackedPath?: (pathname: string, query: string) => string | undefined;
}) {
    const pathname = usePathname();
    const query = useSearchParams().toString();
    const history = useRef<{url?: string; referrer?: string}>({});
    const [ready] = useState(() => {
        if (matomo) {
            initMatomo(matomo);
        }

        return Boolean(matomo);
    });

    useEffect(() => {
        if (ready && authResolved) {
            setUserId(userId);
        }
    }, [ready, authResolved, userId]);

    useEffect(() => {
        if (!ready || !authResolved) {
            return;
        }
        const path = trackedPath(pathname, query);
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
    }, [ready, authResolved, pathname, query, trackedPath]);

    return null;
}
