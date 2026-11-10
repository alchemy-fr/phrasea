// @vitest-environment node
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {
    createSentryTunnelHandler,
    getClientIp,
    parseDsn,
    SENTRY_TUNNEL_PATH,
    sentryTunnelOptionsFromEnv,
    type SentryTunnelOptions,
} from './sentryTunnel';

const DSN = 'https://public@o1.ingest.sentry.io/42';

function envelope(dsn: unknown = DSN, items = ''): string {
    return `${JSON.stringify({event_id: 'e1', dsn})}\n${items}`;
}

function post(
    body: BodyInit | null,
    headers: Record<string, string> = {}
): Request {
    return new Request(`https://databox.example.com${SENTRY_TUNNEL_PATH}`, {
        method: 'POST',
        headers: {'x-real-ip': '10.0.0.1', ...headers},
        body,
    });
}

const fetchMock = vi.fn<typeof fetch>();

function handler(options: Partial<SentryTunnelOptions> = {}) {
    return createSentryTunnelHandler({
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
        .mockImplementation(
            async () => new Response('{"id":"e1"}', {status: 200})
        );
    vi.spyOn(console, 'error').mockImplementation(() => undefined);
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('parseDsn', () => {
    it('reads the host and the project', () => {
        expect(parseDsn(DSN)).toEqual({
            protocol: 'https:',
            host: 'o1.ingest.sentry.io',
            pathPrefix: '',
            projectId: '42',
        });
    });

    it('keeps the port and the path prefix', () => {
        expect(
            parseDsn('http://key@sentry.example.com:9000/prefix/sub/7')
        ).toEqual({
            protocol: 'http:',
            host: 'sentry.example.com:9000',
            pathPrefix: '/prefix/sub',
            projectId: '7',
        });
    });

    it.each([
        'not a url',
        'https://o1.ingest.sentry.io/42',
        'https://key@o1.ingest.sentry.io/',
        'https://key@o1.ingest.sentry.io/project',
        'ftp://key@o1.ingest.sentry.io/42',
    ])('rejects %s', dsn => {
        expect(parseDsn(dsn)).toBeUndefined();
    });
});

describe('getClientIp', () => {
    function request(headers: Record<string, string>) {
        return new Request('https://databox.example.com/', {headers});
    }

    it('prefers the IP set by Traefik', () => {
        expect(
            getClientIp(
                request({
                    'x-real-ip': '10.0.0.1',
                    'x-forwarded-for': '10.0.0.2',
                })
            )
        ).toBe('10.0.0.1');
    });

    it('falls back on the first forwarded IP', () => {
        expect(
            getClientIp(request({'x-forwarded-for': ' 10.0.0.2 , 10.0.0.3'}))
        ).toBe('10.0.0.2');
    });

    it('is unknown without any proxy header', () => {
        expect(getClientIp(request({}))).toBe('unknown');
    });
});

describe('sentryTunnelOptionsFromEnv', () => {
    it('applies the defaults', () => {
        expect(sentryTunnelOptionsFromEnv({})).toEqual({
            dsn: undefined,
            perIpLimit: 30,
            globalLimit: 300,
        });
        expect(
            sentryTunnelOptionsFromEnv({
                SENTRY_DSN: '',
                SENTRY_TUNNEL_RATE_LIMIT_PER_IP: '0',
                SENTRY_TUNNEL_RATE_LIMIT_GLOBAL: 'many',
            })
        ).toEqual({dsn: undefined, perIpLimit: 30, globalLimit: 300});
    });

    it('reads the limits', () => {
        expect(
            sentryTunnelOptionsFromEnv({
                SENTRY_DSN: DSN,
                SENTRY_TUNNEL_RATE_LIMIT_PER_IP: '5',
                SENTRY_TUNNEL_RATE_LIMIT_GLOBAL: '50',
            })
        ).toEqual({dsn: DSN, perIpLimit: 5, globalLimit: 50});
    });
});

describe('createSentryTunnelHandler', () => {
    it('is not found without a DSN', async () => {
        const response = await handler({dsn: undefined})(post(envelope()));

        expect(response.status).toBe(404);
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('is not found with an invalid DSN', async () => {
        const response = await handler({dsn: 'nope'})(post(envelope()));

        expect(response.status).toBe(404);
    });

    it('relays the envelope to the project of the DSN', async () => {
        const body = envelope(DSN, '{"type":"event"}\n{"message":"x"}\n');
        const response = await handler()(post(body));

        expect(response.status).toBe(200);
        expect(await response.text()).toBe('{"id":"e1"}');
        expect(fetchMock).toHaveBeenCalledTimes(1);
        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('https://o1.ingest.sentry.io/api/42/envelope/');
        expect(init?.method).toBe('POST');
        expect(init?.headers).toEqual({
            'Content-Type': 'application/x-sentry-envelope',
            'X-Forwarded-For': '10.0.0.1',
        });
        expect(new TextDecoder().decode(init?.body as Uint8Array)).toBe(body);
    });

    it('keeps the port and the path prefix of the DSN', async () => {
        const dsn = 'http://key@sentry.example.com:9000/prefix/7';
        const response = await handler({dsn})(post(envelope(dsn)));

        expect(response.status).toBe(200);
        expect(fetchMock.mock.calls[0][0]).toBe(
            'http://sentry.example.com:9000/prefix/api/7/envelope/'
        );
    });

    it('passes neither the cookies nor the credentials on', async () => {
        await handler()(
            post(envelope(), {
                cookie: 'session=secret',
                authorization: 'Bearer secret',
            })
        );

        const headers = fetchMock.mock.calls[0][1]?.headers;
        expect(Object.keys(headers ?? {})).toEqual([
            'Content-Type',
            'X-Forwarded-For',
        ]);
    });

    it('does not forward an unknown IP', async () => {
        const request = new Request('https://databox.example.com/api/relay', {
            method: 'POST',
            body: envelope(),
        });
        await handler()(request);

        expect(fetchMock.mock.calls[0][1]?.headers).toEqual({
            'Content-Type': 'application/x-sentry-envelope',
        });
    });

    it('passes a binary body on as is', async () => {
        const header = new TextEncoder().encode(`${envelope()}`);
        const attachment = new Uint8Array([0, 255, 1, 254, 0x0a, 128, 0]);
        const body = new Uint8Array(header.length + attachment.length);
        body.set(header);
        body.set(attachment, header.length);

        const response = await handler()(post(body));

        expect(response.status).toBe(200);
        expect(
            Array.from(fetchMock.mock.calls[0][1]?.body as Uint8Array)
        ).toEqual(Array.from(body));
    });

    it.each([
        ['an envelope without header', ''],
        ['an envelope with a header without DSN', '{"event_id":"e1"}\n'],
        ['invalid JSON', '{"dsn": \n{}'],
        ['an invalid DSN', envelope('nope')],
        [
            'the DSN of another host',
            envelope('https://public@o2.ingest.sentry.io/42'),
        ],
        [
            'the DSN of another project',
            envelope('https://public@o1.ingest.sentry.io/43'),
        ],
    ])('rejects %s', async (_, body) => {
        const response = await handler()(post(body));

        expect(response.status).toBe(400);
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('rejects a body announced too large', async () => {
        const response = await handler({maxBodyBytes: 10})(
            post(envelope(), {'content-length': '11'})
        );

        expect(response.status).toBe(413);
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('rejects a body too large', async () => {
        const response = await handler({maxBodyBytes: 10})(post(envelope()));

        expect(response.status).toBe(413);
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('rejects a streamed body too large', async () => {
        const chunk = new TextEncoder().encode(envelope());
        const stream = new ReadableStream<Uint8Array>({
            start(controller) {
                controller.enqueue(chunk);
                controller.enqueue(chunk);
                controller.close();
            },
        });
        const request = new Request('https://databox.example.com/api/relay', {
            method: 'POST',
            body: stream,
            // @ts-expect-error Node needs it to send a stream
            duplex: 'half',
        });

        const response = await handler({maxBodyBytes: chunk.length + 1})(
            request
        );

        expect(response.status).toBe(413);
    });

    it('limits the envelopes per IP', async () => {
        const tunnel = handler({perIpLimit: 2});
        expect((await tunnel(post(envelope()))).status).toBe(200);
        expect((await tunnel(post(envelope()))).status).toBe(200);

        const response = await tunnel(post(envelope()));
        expect(response.status).toBe(429);
        expect(Number(response.headers.get('Retry-After'))).toBeGreaterThan(0);

        const other = await tunnel(post(envelope(), {'x-real-ip': '10.0.0.2'}));
        expect(other.status).toBe(200);
        expect(fetchMock).toHaveBeenCalledTimes(3);
    });

    it('limits the envelopes of every IP together', async () => {
        const tunnel = handler({globalLimit: 2});
        for (const ip of ['10.0.0.1', '10.0.0.2']) {
            expect(
                (await tunnel(post(envelope(), {'x-real-ip': ip}))).status
            ).toBe(200);
        }

        const response = await tunnel(
            post(envelope(), {'x-real-ip': '10.0.0.3'})
        );
        expect(response.status).toBe(429);
        expect(response.headers.get('Retry-After')).toBeTruthy();
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('passes the status and the rate limits of Sentry on', async () => {
        fetchMock.mockResolvedValue(
            new Response('', {
                status: 429,
                headers: {
                    'Retry-After': '60',
                    'X-Sentry-Rate-Limits': '60:error:organization',
                    'Set-Cookie': 'sentry=1',
                },
            })
        );

        const response = await handler()(post(envelope()));

        expect(response.status).toBe(429);
        expect(response.headers.get('Retry-After')).toBe('60');
        expect(response.headers.get('X-Sentry-Rate-Limits')).toBe(
            '60:error:organization'
        );
        expect(response.headers.get('Set-Cookie')).toBeNull();
    });

    it('answers a status without body', async () => {
        fetchMock.mockResolvedValue(new Response(null, {status: 204}));

        const response = await handler()(post(envelope()));

        expect(response.status).toBe(204);
    });

    it('answers 502 when Sentry fails', async () => {
        fetchMock.mockRejectedValue(new TypeError('fetch failed'));

        const response = await handler()(post(envelope()));

        expect(response.status).toBe(502);
    });

    it('answers 502 when Sentry is too slow', async () => {
        fetchMock.mockImplementation(
            (_, init) =>
                new Promise((_resolve, reject) => {
                    init?.signal?.addEventListener('abort', () =>
                        reject(init.signal?.reason)
                    );
                })
        );

        const response = await handler({timeoutMs: 10})(post(envelope()));

        expect(response.status).toBe(502);
    });
});
