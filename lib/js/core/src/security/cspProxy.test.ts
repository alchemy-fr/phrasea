// @vitest-environment node
import {describe, expect, it} from 'vitest';
import {NextRequest} from 'next/server';
import {getScriptNonceFromHeaders} from 'next/dist/server/app-render/get-script-nonce-from-header';
import {cspResponse, NONCE_HEADER} from './cspProxy';

const DSN = 'https://key@sentry.test/42';

function request(headers: Record<string, string> = {}): NextRequest {
    return new NextRequest('https://databox.test/assets', {headers});
}

/** The request headers Next is given, as `NextResponse.next()` encodes them */
function forwarded(response: Response, name: string): string | null {
    return response.headers.get(`x-middleware-request-${name.toLowerCase()}`);
}

/**
 * The nonce Next puts on its scripts: it reads it from the request headers
 * once the response headers of the proxy have been copied onto them.
 */
function nonceSeenByNext(response: Response): string | undefined {
    const headers: Record<string, string> = {};
    for (const name of [
        'Content-Security-Policy',
        'Content-Security-Policy-Report-Only',
    ]) {
        const value =
            response.headers.get(name) ??
            forwarded(response, name) ??
            undefined;
        if (value) {
            headers[name.toLowerCase()] = value;
        }
    }

    return getScriptNonceFromHeaders(headers);
}

describe('cspResponse', () => {
    it('reports the policy only by default, still enforcing the given directives', () => {
        const response = cspResponse(
            request(),
            {frameSrc: ['https://k.test'], frameAncestors: []},
            {env: {}, enforced: ['frame-src', 'frame-ancestors']}
        );

        const policy = response.headers.get(
            'Content-Security-Policy-Report-Only'
        );
        const nonce = forwarded(response, NONCE_HEADER);
        expect(nonce).toBeTruthy();
        expect(policy).toContain(
            `script-src 'self' 'nonce-${nonce}' 'strict-dynamic'`
        );
        expect(response.headers.get('Content-Security-Policy')).toBe(
            "frame-src 'self' https://k.test; frame-ancestors 'self'; " +
                `script-src * data: blob: 'unsafe-inline' 'unsafe-eval' 'nonce-${nonce}'`
        );
        expect(nonceSeenByNext(response)).toBe(nonce);
        expect(policy).not.toContain('report-');
        expect(response.headers.get('Reporting-Endpoints')).toBeNull();
    });

    it('sends no enforced policy when no directive is', () => {
        const response = cspResponse(request(), {}, {env: {}});

        expect(response.headers.get('Content-Security-Policy')).toBeNull();
        expect(
            response.headers.get('Content-Security-Policy-Report-Only')
        ).toBeTruthy();
        expect(nonceSeenByNext(response)).toBe(
            forwarded(response, NONCE_HEADER)
        );
    });

    it('enforces the policy when CSP_REPORT_ONLY is false, and reports to the relay', () => {
        const response = cspResponse(
            request({
                'Content-Security-Policy': "script-src 'nonce-forged'",
                [NONCE_HEADER]: 'forged',
            }),
            {},
            {env: {CSP_REPORT_ONLY: 'false', SENTRY_DSN: DSN}}
        );

        const policy = response.headers.get('Content-Security-Policy');
        expect(
            response.headers.get('Content-Security-Policy-Report-Only')
        ).toBeNull();
        expect(policy).toContain('report-uri /api/relay/csp; report-to csp');
        expect(response.headers.get('Reporting-Endpoints')).toBe(
            'csp="/api/relay/csp"'
        );
        const nonce = forwarded(response, NONCE_HEADER);
        expect(nonce).not.toBe('forged');
        expect(forwarded(response, 'Content-Security-Policy')).toBe(policy);
        expect(nonceSeenByNext(response)).toBe(nonce);
    });

    it('generates a new nonce for every request', () => {
        const nonce = () =>
            forwarded(cspResponse(request(), {}, {env: {}}), NONCE_HEADER);
        expect(nonce()).not.toBe(nonce());
    });
});
