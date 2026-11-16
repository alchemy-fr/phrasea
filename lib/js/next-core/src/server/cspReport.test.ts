// @vitest-environment node
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {
    createCspReportHandler,
    CSP_REPORT_PATH,
    cspReportOptionsFromEnv,
    securityEndpoint,
    type CspReportOptions,
} from './cspReport';

const DSN = 'https://public@o1.ingest.sentry.io/42';
const REPORT = JSON.stringify({
    'csp-report': {'violated-directive': 'script-src-elem'},
});

function post(
    body: BodyInit | null,
    headers: Record<string, string> = {}
): Request {
    return new Request(`https://databox.example.com${CSP_REPORT_PATH}`, {
        method: 'POST',
        headers: {
            'x-real-ip': '10.0.0.1',
            'content-type': 'application/csp-report',
            ...headers,
        },
        body,
    });
}

const fetchMock = vi.fn<typeof fetch>();

function handler(options: Partial<CspReportOptions> = {}) {
    return createCspReportHandler({
        dsn: DSN,
        perIpLimit: 30,
        globalLimit: 300,
        fetchImpl: fetchMock,
        ...options,
    });
}

beforeEach(() => {
    fetchMock
        .mockReset()
        .mockImplementation(async () => new Response('', {status: 200}));
    vi.spyOn(console, 'error').mockImplementation(() => undefined);
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('securityEndpoint', () => {
    it('targets the security endpoint of the project with the public key', () => {
        expect(
            securityEndpoint('https://key@sentry.test/path/42', {
                environment: 'prod',
                release: '1.2',
            })
        ).toBe(
            'https://sentry.test/path/api/42/security/?sentry_key=key&sentry_environment=prod&sentry_release=1.2'
        );
        expect(securityEndpoint('not a dsn')).toBeUndefined();
    });
});

describe('cspReportOptionsFromEnv', () => {
    it('reads the DSN, the environment, the release and the tunnel limits', () => {
        expect(
            cspReportOptionsFromEnv({
                SENTRY_DSN: DSN,
                SENTRY_ENVIRONMENT: 'prod',
                SENTRY_RELEASE: '',
                SENTRY_TUNNEL_RATE_LIMIT_PER_IP: '5',
            })
        ).toEqual({
            dsn: DSN,
            environment: 'prod',
            release: undefined,
            perIpLimit: 5,
            globalLimit: 300,
        });
    });
});

describe('createCspReportHandler', () => {
    it('relays the report to Sentry', async () => {
        const response = await handler({environment: 'prod'})(
            post(REPORT, {
                'content-type': 'application/csp-report; charset=utf-8',
            })
        );

        expect(response.status).toBe(204);
        expect(fetchMock).toHaveBeenCalledOnce();
        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe(
            'https://o1.ingest.sentry.io/api/42/security/?sentry_key=public&sentry_environment=prod'
        );
        expect(init?.headers).toEqual({
            'Content-Type': 'application/csp-report',
            'X-Forwarded-For': '10.0.0.1',
        });
        expect(new TextDecoder().decode(init?.body as Uint8Array)).toBe(REPORT);
    });

    it('accepts the batches of the Reporting API', async () => {
        const response = await handler()(
            post('[]', {'content-type': 'application/reports+json'})
        );

        expect(response.status).toBe(204);
    });

    it('is disabled without a valid DSN', async () => {
        for (const dsn of [undefined, 'not a dsn']) {
            const response = await handler({dsn})(post(REPORT));
            expect(response.status).toBe(404);
        }
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('rejects what is not a report', async () => {
        expect(
            (await handler()(post(REPORT, {'content-type': 'text/plain'})))
                .status
        ).toBe(415);
        expect((await handler()(post(''))).status).toBe(400);
        expect((await handler({maxBodyBytes: 10})(post(REPORT))).status).toBe(
            413
        );
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('limits the reports per client IP', async () => {
        const relay = handler({perIpLimit: 1});

        expect((await relay(post(REPORT))).status).toBe(204);
        const limited = await relay(post(REPORT));
        expect(limited.status).toBe(429);
        expect(limited.headers.get('Retry-After')).toBeTruthy();
        expect(
            (await relay(post(REPORT, {'x-real-ip': '10.0.0.2'}))).status
        ).toBe(204);
    });

    it('answers 502 when Sentry fails', async () => {
        fetchMock.mockImplementationOnce(
            async () => new Response('', {status: 500})
        );
        expect((await handler()(post(REPORT))).status).toBe(502);

        fetchMock.mockRejectedValueOnce(new Error('down'));
        expect((await handler()(post(REPORT))).status).toBe(502);
    });
});
