// @vitest-environment node
import {describe, expect, it} from 'vitest';
import {
    buildCsp,
    cspReportFromEnv,
    CSP_REPORT_GROUP,
    CSP_REPORT_PATH,
    generateNonce,
    isCspReportOnly,
    mergeCspSources,
    originOf,
    pickDirectives,
    sourceList,
    stackCspSources,
} from './csp';

describe('originOf', () => {
    it('keeps the scheme, the host and the port only', () => {
        expect(originOf('https://keycloak.test:8443/auth/')).toBe(
            'https://keycloak.test:8443'
        );
    });

    it('ignores what is not an URL', () => {
        expect(originOf(undefined)).toBeUndefined();
        expect(originOf('  ')).toBeUndefined();
        expect(originOf('not an url')).toBeUndefined();
        expect(originOf('data:text/plain,x')).toBeUndefined();
    });
});

describe('sourceList', () => {
    it('splits on white space and drops duplicates', () => {
        expect(
            sourceList(
                "'self'",
                undefined,
                ' https://a.test  https://b.test ',
                'https://a.test'
            )
        ).toBe("'self' https://a.test https://b.test");
    });
});

describe('mergeCspSources', () => {
    it('concatenates the sources of each directive', () => {
        expect(
            mergeCspSources(
                {connectSrc: ['https://a.test'], imgSrc: ['https://i.test']},
                {connectSrc: ['https://b.test'], frameAncestors: []}
            )
        ).toEqual({
            connectSrc: ['https://a.test', 'https://b.test'],
            imgSrc: ['https://i.test'],
            frameAncestors: [],
        });
    });
});

describe('generateNonce', () => {
    it('returns 128 random bits in base64, different every time', () => {
        const nonce = generateNonce();
        expect(nonce).toMatch(/^[A-Za-z0-9+/]{22}==$/);
        expect(generateNonce()).not.toBe(nonce);
    });
});

describe('buildCsp', () => {
    it('only allows the scripts bearing the nonce by default', () => {
        expect(buildCsp({nonce: 'abc'}).split('; ')).toEqual([
            "default-src 'self'",
            "script-src 'self' 'nonce-abc' 'strict-dynamic'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "media-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-src 'self'",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    });

    it('adds the sources of the page, the dev server and the reporting', () => {
        const policy = buildCsp({
            nonce: 'abc',
            dev: true,
            scriptSrc: ['https://matomo.test'],
            connectSrc: ['https://api.test', undefined, 'wss://ws.test'],
            imgSrc: ['https://s3.test'],
            mediaSrc: ['https://s3.test'],
            fontSrc: ['https://fonts.test'],
            frameSrc: ['https://keycloak.test'],
            workerSrc: ['https://workers.test'],
            formAction: ['https://keycloak.test'],
            frameAncestors: ['https://a.test'],
            report: {uri: '/report', group: 'csp'},
        });

        expect(policy.split('; ')).toEqual([
            "default-src 'self'",
            "script-src 'self' 'nonce-abc' 'strict-dynamic' https://matomo.test 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https://s3.test",
            "media-src 'self' data: blob: https://s3.test",
            "font-src 'self' data: https://fonts.test",
            "connect-src 'self' https://api.test wss://ws.test",
            "frame-src 'self' https://keycloak.test",
            "worker-src 'self' blob: https://workers.test",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self' https://keycloak.test",
            "frame-ancestors 'self' https://a.test",
            'report-uri /report',
            'report-to csp',
        ]);
    });

    it('restricts the embedding sites to the same origin with an empty list', () => {
        expect(buildCsp({nonce: 'abc', frameAncestors: []})).toContain(
            "frame-ancestors 'self'"
        );
    });
});

describe('pickDirectives', () => {
    it('keeps the given directives only', () => {
        expect(
            pickDirectives(
                buildCsp({
                    nonce: 'abc',
                    frameSrc: ['https://k.test'],
                    frameAncestors: ['https://a.test'],
                }),
                ['frame-src', 'frame-ancestors']
            )
        ).toBe(
            "frame-src 'self' https://k.test; frame-ancestors 'self' https://a.test"
        );
        expect(pickDirectives(buildCsp({nonce: 'abc'}), [])).toBe('');
    });
});

describe('isCspReportOnly', () => {
    it('only reports unless explicitly disabled', () => {
        expect(isCspReportOnly({})).toBe(true);
        expect(isCspReportOnly({CSP_REPORT_ONLY: ''})).toBe(true);
        expect(isCspReportOnly({CSP_REPORT_ONLY: 'true'})).toBe(true);
        expect(isCspReportOnly({CSP_REPORT_ONLY: 'false'})).toBe(false);
        expect(isCspReportOnly({CSP_REPORT_ONLY: ' 0 '})).toBe(false);
    });
});

describe('cspReportFromEnv', () => {
    it('reports to the relay when the DSN is valid', () => {
        expect(
            cspReportFromEnv({SENTRY_DSN: 'https://key@sentry.test/42'})
        ).toEqual({uri: CSP_REPORT_PATH, group: CSP_REPORT_GROUP});
        expect(cspReportFromEnv({})).toBeUndefined();
        expect(cspReportFromEnv({SENTRY_DSN: 'not a dsn'})).toBeUndefined();
    });
});

describe('stackCspSources', () => {
    it('allows Keycloak, the buckets, the CDN, Matomo and the extra sources', () => {
        expect(
            stackCspSources({
                KEYCLOAK_URL: 'https://keycloak.test/auth',
                MATOMO_URL: 'https://matomo.test/',
                CONFIGURATOR_S3_ENDPOINT: 'https://s3.test:8443',
                CLOUD_FRONT_URL: 'https://cdn.test/files',
                CSP_EXTRA_SOURCES: 'https://logo.test',
            })
        ).toEqual({
            scriptSrc: ['https://matomo.test'],
            connectSrc: [
                'https://keycloak.test',
                'https://matomo.test',
                'https://s3.test:8443',
                'https://*.s3.test:8443',
                'https://cdn.test',
                'https://logo.test',
            ],
            imgSrc: [
                'https://matomo.test',
                'https://s3.test:8443',
                'https://*.s3.test:8443',
                'https://cdn.test',
                'https://logo.test',
            ],
            mediaSrc: [
                'https://s3.test:8443',
                'https://*.s3.test:8443',
                'https://cdn.test',
                'https://logo.test',
            ],
            frameSrc: ['https://keycloak.test'],
            formAction: ['https://keycloak.test'],
        });
    });

    it('does not allow the subdomains of a path-style endpoint', () => {
        const {connectSrc} = stackCspSources({
            CONFIGURATOR_S3_ENDPOINT: 'https://minio.test',
            CONFIGURATOR_S3_USE_PATH_STYLE_ENDPOINT: 'true',
        });
        expect(sourceList(...(connectSrc ?? []))).toBe('https://minio.test');
    });
});
