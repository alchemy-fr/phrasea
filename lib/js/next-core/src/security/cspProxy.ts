import {NextResponse, type NextRequest} from 'next/server';
import {
    buildCsp,
    cspReportFromEnv,
    generateNonce,
    isCspReportOnly,
    NONCE_HEADER,
    pickDirectives,
    type CspSources,
} from './csp';

export {NONCE_HEADER};

export type CspProxyOptions = {
    env?: Record<string, string | undefined>;
    /**
     * The directives still enforced while the policy is only reported (they
     * were enforced before it existed). `frame-ancestors` is ignored by the
     * browsers in a report-only policy.
     */
    enforced?: string[];
};

/**
 * The `script-src` of the policy enforced next to a report-only one: it only
 * carries the nonce for Next, which copies the response headers of the proxy
 * onto the request and reads the nonce from its `Content-Security-Policy`
 * (the report-only header is only read when there is none). Any script
 * source stays allowed, but the nonce makes the browsers ignore
 * `'unsafe-inline'`: inline scripts and event handlers without it are
 * blocked.
 */
export function nonceCarrierScriptSrc(nonce: string): string {
    return `script-src * data: blob: 'unsafe-inline' 'unsafe-eval' 'nonce-${nonce}'`;
}

/**
 * The response of the proxy (`src/proxy.ts`) of a Next.js client: a fresh
 * nonce, the Content Security Policy built with it, report-only unless
 * `CSP_REPORT_ONLY` is false.
 *
 * Next reads the nonce from the `Content-Security-Policy` header of the
 * request and puts it on its scripts; the server components read it from
 * `x-nonce` (`headers()`) for the inline scripts they render. Both request
 * headers are overwritten, whatever the browser sent, and Next then
 * overwrites the request headers again with the response headers of the
 * proxy.
 */
export function cspResponse(
    request: NextRequest,
    sources: CspSources,
    {env = process.env, enforced = []}: CspProxyOptions = {}
): NextResponse {
    const nonce = generateNonce();
    const report = cspReportFromEnv(env);
    const policy = buildCsp({
        ...sources,
        nonce,
        dev: process.env.NODE_ENV === 'development',
        report,
    });

    const requestHeaders = new Headers(request.headers);
    requestHeaders.set(NONCE_HEADER, nonce);
    requestHeaders.set('Content-Security-Policy', policy);
    requestHeaders.delete('Content-Security-Policy-Report-Only');

    const response = NextResponse.next({request: {headers: requestHeaders}});
    if (isCspReportOnly(env)) {
        response.headers.set('Content-Security-Policy-Report-Only', policy);
        const enforcedPolicy = pickDirectives(policy, enforced);
        if (enforcedPolicy) {
            response.headers.set(
                'Content-Security-Policy',
                `${enforcedPolicy}; ${nonceCarrierScriptSrc(nonce)}`
            );
        }
    } else {
        response.headers.set('Content-Security-Policy', policy);
    }
    if (report) {
        response.headers.set(
            'Reporting-Endpoints',
            `${report.group}="${report.uri}"`
        );
    }

    return response;
}
