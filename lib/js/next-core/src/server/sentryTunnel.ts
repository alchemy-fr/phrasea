import 'server-only';
import {createFixedWindowLimiter} from './rateLimit';
import {
    parseDsn,
    SENTRY_TUNNEL_DSN,
    type ParsedDsn,
} from '../monitoring/tunnelDsn';

export {SENTRY_TUNNEL_PATH} from '../monitoring/tunnelPath';
export {parseDsn, SENTRY_TUNNEL_DSN, type ParsedDsn};

export const DEFAULT_PER_IP_LIMIT = 30;
export const DEFAULT_GLOBAL_LIMIT = 300;
export const DEFAULT_MAX_BODY_BYTES = 1024 * 1024;

const WINDOW_MS = 60_000;
const UPSTREAM_TIMEOUT_MS = 5_000;
/** Headers of the Sentry answer the SDK reads to back off */
const FORWARDED_RESPONSE_HEADERS = ['Retry-After', 'X-Sentry-Rate-Limits'];
/** A `Response` with one of these statuses must not have a body */
const NULL_BODY_STATUSES = [101, 103, 204, 205, 304];

export type SentryTunnelOptions = {
    /** The DSN of the server: the only Sentry project envelopes are relayed to */
    dsn: string | undefined;
    /** Envelopes per minute and per client IP */
    perIpLimit: number;
    /** Envelopes per minute, all clients included */
    globalLimit: number;
    maxBodyBytes?: number;
    timeoutMs?: number;
    fetchImpl?: typeof fetch;
};

function positiveInt(value: string | undefined): number | undefined {
    const n = parseInt(value ?? '', 10);

    return Number.isNaN(n) || n <= 0 ? undefined : n;
}

/** The tunnel options from `SENTRY_DSN` and `SENTRY_TUNNEL_RATE_LIMIT_*` */
export function sentryTunnelOptionsFromEnv(
    env: Record<string, string | undefined>
): SentryTunnelOptions {
    return {
        dsn: env.SENTRY_DSN || undefined,
        perIpLimit:
            positiveInt(env.SENTRY_TUNNEL_RATE_LIMIT_PER_IP) ??
            DEFAULT_PER_IP_LIMIT,
        globalLimit:
            positiveInt(env.SENTRY_TUNNEL_RATE_LIMIT_GLOBAL) ??
            DEFAULT_GLOBAL_LIMIT,
    };
}

/**
 * The client IP, `unknown` without any proxy header.
 *
 * `x-real-ip` is set by Traefik / the ingress from the connection, overwriting
 * whatever the client sent. Otherwise the last `x-forwarded-for` entry, the
 * one appended by the nearest proxy: the first ones come from the client and
 * would let it pick its rate limit bucket.
 */
export function getClientIp(request: Request): string {
    const realIp = request.headers.get('x-real-ip')?.trim();
    if (realIp) {
        return realIp;
    }
    const forwardedFor = request.headers
        .get('x-forwarded-for')
        ?.split(',')
        .pop()
        ?.trim();

    return forwardedFor || 'unknown';
}

function error(
    message: string,
    status: number,
    headers?: Record<string, string>
): Response {
    return Response.json({message}, {status, headers});
}

/**
 * Reads at most `maxBytes` of the body, undefined beyond: the
 * `content-length` header may be missing or lie.
 */
async function readBody(
    request: Request,
    maxBytes: number
): Promise<Uint8Array<ArrayBuffer> | undefined> {
    if (!request.body) {
        return new Uint8Array();
    }
    const reader = request.body.getReader();
    const chunks: Uint8Array[] = [];
    let length = 0;
    for (;;) {
        const {done, value} = await reader.read();
        if (done) {
            break;
        }
        length += value.byteLength;
        if (length > maxBytes) {
            await reader.cancel();

            return undefined;
        }
        chunks.push(value);
    }
    const body = new Uint8Array(length);
    let offset = 0;
    for (const chunk of chunks) {
        body.set(chunk, offset);
        offset += chunk.byteLength;
    }

    return body;
}

type EnvelopeHeader = {
    /** The parsed JSON of the first line */
    header: Record<string, unknown> & {dsn: string};
    /** Offset of what follows the first line (its line feed included) */
    end: number;
};

/** The header of the envelope (its first line), undefined without a DSN */
function readEnvelopeHeader(body: Uint8Array): EnvelopeHeader | undefined {
    const newLine = body.indexOf(0x0a);
    const end = newLine === -1 ? body.length : newLine;
    let header: unknown;
    try {
        header = JSON.parse(new TextDecoder().decode(body.subarray(0, end)));
    } catch {
        return undefined;
    }
    if (
        !header ||
        typeof header !== 'object' ||
        Array.isArray(header) ||
        typeof (header as {dsn?: unknown}).dsn !== 'string'
    ) {
        return undefined;
    }

    return {header: header as EnvelopeHeader['header'], end};
}

function isSameProject(a: ParsedDsn, b: ParsedDsn): boolean {
    return a.host === b.host && a.projectId === b.projectId;
}

/**
 * The envelope with the given DSN in its header, the items copied byte for
 * byte (their lengths only cover the items)
 */
function withEnvelopeDsn(
    body: Uint8Array,
    {header, end}: EnvelopeHeader,
    dsn: string
): Uint8Array<ArrayBuffer> {
    const headerLine = new TextEncoder().encode(
        JSON.stringify({...header, dsn})
    );
    const rest = body.subarray(end);
    const envelope = new Uint8Array(headerLine.length + rest.length);
    envelope.set(headerLine);
    envelope.set(rest, headerLine.length);

    return envelope;
}

/**
 * Route handler relaying the envelopes of the browser SDK (option `tunnel`)
 * to the Sentry project of the server DSN, behind a per IP and a global rate
 * limit. Both limits are kept in memory: they apply per process, thus per
 * replica.
 *
 * The browser SDK is given `SENTRY_TUNNEL_DSN`, never the DSN of the server:
 * the DSN of the envelope header is replaced with the latter before relaying.
 * Envelopes still carrying the real DSN (pages loaded before it was hidden)
 * are accepted too.
 *
 * Not an open relay: the envelope must target the tunnel or the project of the
 * server DSN, and nothing but the envelope and the client IP is passed on.
 */
export function createSentryTunnelHandler({
    dsn,
    perIpLimit,
    globalLimit,
    maxBodyBytes = DEFAULT_MAX_BODY_BYTES,
    timeoutMs = UPSTREAM_TIMEOUT_MS,
    fetchImpl = fetch,
}: SentryTunnelOptions): (request: Request) => Promise<Response> {
    const upstream = dsn ? parseDsn(dsn) : undefined;
    if (dsn && !upstream) {
        console.error('[sentry-tunnel] invalid SENTRY_DSN, tunnel disabled');
    }
    const tunnel = parseDsn(SENTRY_TUNNEL_DSN)!;
    const upstreamUrl =
        upstream &&
        `${upstream.protocol}//${upstream.host}${upstream.pathPrefix}/api/${upstream.projectId}/envelope/`;
    const perIp = createFixedWindowLimiter({
        max: perIpLimit,
        windowMs: WINDOW_MS,
    });
    const global = createFixedWindowLimiter({
        max: globalLimit,
        windowMs: WINDOW_MS,
    });

    return async request => {
        if (!dsn || !upstream || !upstreamUrl) {
            return error('Not found', 404);
        }

        const contentLength = parseInt(
            request.headers.get('content-length') ?? '',
            10
        );
        if (contentLength > maxBodyBytes) {
            return error('Envelope too large', 413);
        }

        const ip = getClientIp(request);
        const ipLimit = perIp.consume(ip);
        const limit = ipLimit.allowed ? global.consume('*') : ipLimit;
        if (!limit.allowed) {
            return error('Too many envelopes', 429, {
                'Retry-After': String(limit.retryAfter),
            });
        }

        const body = await readBody(request, maxBodyBytes);
        if (!body) {
            return error('Envelope too large', 413);
        }

        const envelopeHeader = readEnvelopeHeader(body);
        const target = envelopeHeader && parseDsn(envelopeHeader.header.dsn);
        if (
            !envelopeHeader ||
            !target ||
            !(isSameProject(target, tunnel) || isSameProject(target, upstream))
        ) {
            return error('Invalid envelope', 400);
        }

        const headers: Record<string, string> = {
            'Content-Type': 'application/x-sentry-envelope',
        };
        if (ip !== 'unknown') {
            headers['X-Forwarded-For'] = ip;
        }

        let response: Response;
        let responseBody: ArrayBuffer;
        try {
            response = await fetchImpl(upstreamUrl, {
                method: 'POST',
                headers,
                body: withEnvelopeDsn(body, envelopeHeader, dsn),
                signal: AbortSignal.timeout(timeoutMs),
            });
            responseBody = await response.arrayBuffer();
        } catch (e) {
            console.error('[sentry-tunnel] upstream request failed:', e);

            return error('Sentry is unreachable', 502);
        }

        const responseHeaders = new Headers();
        for (const name of [...FORWARDED_RESPONSE_HEADERS, 'Content-Type']) {
            const value = response.headers.get(name);
            if (value) {
                responseHeaders.set(name, value);
            }
        }

        return new Response(
            NULL_BODY_STATUSES.includes(response.status) ? null : responseBody,
            {
                status: response.status,
                headers: responseHeaders,
            }
        );
    };
}
