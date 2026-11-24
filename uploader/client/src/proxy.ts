import type {NextRequest} from 'next/server';
import {cspResponse} from '@alchemy/next-core/security/cspProxy';
import {contentSecuritySources} from '@/lib/security/csp';

/**
 * Sets the Content Security Policy of the pages at request time, with a nonce
 * of its own: the sources (API, buckets, Keycloak…) come from the container
 * environment, not from the build.
 */
export function proxy(request: NextRequest) {
    return cspResponse(request, contentSecuritySources(process.env));
}

export const config = {
    // The page of the Keycloak silent sign-in is static: its inline script
    // cannot bear the nonce
    matcher: [
        '/((?!_next/static|_next/image|favicon.svg|favicon.ico|apple-touch-icon.png|silent-check-sso.html).*)',
    ],
};
