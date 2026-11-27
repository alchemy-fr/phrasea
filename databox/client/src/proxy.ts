import {NextResponse, type NextRequest} from 'next/server';
import {cspResponse} from '@alchemy/core/security/cspProxy';
import {contentSecuritySources} from '@/lib/security/csp';
import {notificationRedirect} from '@/features/notifications/notificationUri';

/**
 * Sets the Content Security Policy of the pages at request time, with a nonce
 * of its own: the sources (API, buckets, Keycloak…) come from the container
 * environment, not from the build.
 */
export function proxy(request: NextRequest) {
    // The deep links of the emails must land with a full page load: reached
    // by a client-side navigation, the screen opened above the page would
    // take `/notification-uri` as the place to go back to on close, which
    // redirects to it again. Next turns a same-host Location into a relative
    // one, so the public host behind the reverse proxy is kept.
    const redirect = notificationRedirect(
        request.nextUrl.pathname,
        request.nextUrl.searchParams
    );
    if (redirect) {
        return NextResponse.redirect(new URL(redirect, request.nextUrl), 307);
    }

    return cspResponse(request, contentSecuritySources(process.env));
}

export const config = {
    // The page of the Keycloak silent sign-in is static: its inline script
    // cannot bear the nonce
    matcher: [
        '/((?!_next/static|_next/image|favicon.svg|favicon.ico|apple-touch-icon.png|icons/|manifest.webmanifest|robots.txt|silent-check-sso.html).*)',
    ],
};
