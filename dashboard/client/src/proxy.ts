import {NextResponse} from 'next/server';
import {buildContentSecurityPolicy} from '@/lib/security/csp';

/**
 * Sets the Content Security Policy of the pages at request time: the sites
 * allowed to embed them (the clients of the stack, `ALLOWED_FRAME_ANCESTORS`)
 * come from the container environment, not from the build.
 */
export function proxy() {
    const response = NextResponse.next();
    response.headers.set(
        'Content-Security-Policy',
        buildContentSecurityPolicy(process.env)
    );

    return response;
}

export const config = {
    matcher: ['/((?!_next/static|_next/image|favicon.svg).*)'],
};
