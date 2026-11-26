import 'server-only';
import {createFixedWindowLimiter} from './rateLimit';
import {
    getClientIp,
    readBody,
    sentryTunnelOptionsFromEnv,
} from './sentryTunnel';
import {parseDsn} from '../monitoring/tunnelDsn';

export {CSP_REPORT_PATH} from '../security/csp';

export const DEFAULT_MAX_REPORT_BYTES = 64 * 1024;

const WINDOW_MS = 60_000;
const UPSTREAM_TIMEOUT_MS = 5_000;

/**
 * The bodies of the reports: `report-uri` (one violation) and `report-to`
 * (the Reporting API, a batch)
 */
const REPORT_CONTENT_TYPES = [
    'application/csp-report',
    'application/reports+json',
    'application/json',
];

export type CspReportOptions = {
    /** The DSN of the server: reports go to the security endpoint of its project */
    dsn: string | undefined;
    environment?: string;
    release?: string;
    /** Reports per minute and per client IP */
    perIpLimit: number;
    /** Reports per minute, all clients included */
    globalLimit: number;
    maxBodyBytes?: number;
    timeoutMs?: number;
    fetchImpl?: typeof fetch;
};

/** The relay options: same DSN and rate limits as the Sentry tunnel */
export function cspReportOptionsFromEnv(
    env: Record<string, string | undefined>
): CspReportOptions {
    return {
        ...sentryTunnelOptionsFromEnv(env),
        environment: env.SENTRY_ENVIRONMENT || undefined,
        release: env.SENTRY_RELEASE || undefined,
    };
}

/** The security report endpoint of the Sentry project of a DSN */
export function securityEndpoint(
    dsn: string,
    {environment, release}: {environment?: string; release?: string} = {}
): string | undefined {
    const parsed = parseDsn(dsn);
    if (!parsed) {
        return undefined;
    }
    const query = new URLSearchParams({
        sentry_key: decodeURIComponent(new URL(dsn).username),
    });
    if (environment) {
        query.set('sentry_environment', environment);
    }
    if (release) {
        query.set('sentry_release', release);
    }

    return `${parsed.protocol}//${parsed.host}${parsed.pathPrefix}/api/${parsed.projectId}/security/?${query}`;
}

/**
 * Route handler relaying the Content Security Policy violation reports of the
 * browser to the security endpoint of the Sentry project of the server DSN,
 * which the pages never see (the policy reports to `CSP_REPORT_PATH`), behind
 * a per IP and a global rate limit kept in memory (per replica).
 *
 * Only the report body, its content type and the client IP are passed on.
 */
export function createCspReportHandler({
    dsn,
    environment,
    release,
    perIpLimit,
    globalLimit,
    maxBodyBytes = DEFAULT_MAX_REPORT_BYTES,
    timeoutMs = UPSTREAM_TIMEOUT_MS,
    fetchImpl = fetch,
}: CspReportOptions): (request: Request) => Promise<Response> {
    const upstreamUrl = dsn
        ? securityEndpoint(dsn, {environment, release})
        : undefined;
    const perIp = createFixedWindowLimiter({
        max: perIpLimit,
        windowMs: WINDOW_MS,
    });
    const global = createFixedWindowLimiter({
        max: globalLimit,
        windowMs: WINDOW_MS,
    });

    return async request => {
        if (!upstreamUrl) {
            return new Response(null, {status: 404});
        }

        const contentType = (request.headers.get('content-type') ?? '')
            .split(';')[0]
            .trim()
            .toLowerCase();
        if (!REPORT_CONTENT_TYPES.includes(contentType)) {
            return new Response(null, {status: 415});
        }

        const contentLength = parseInt(
            request.headers.get('content-length') ?? '',
            10
        );
        if (contentLength > maxBodyBytes) {
            return new Response(null, {status: 413});
        }

        const ip = getClientIp(request);
        const ipLimit = perIp.consume(ip);
        const limit = ipLimit.allowed ? global.consume('*') : ipLimit;
        if (!limit.allowed) {
            return new Response(null, {
                status: 429,
                headers: {'Retry-After': String(limit.retryAfter)},
            });
        }

        const body = await readBody(request, maxBodyBytes);
        if (!body) {
            return new Response(null, {status: 413});
        }
        if (body.byteLength === 0) {
            return new Response(null, {status: 400});
        }

        const headers: Record<string, string> = {'Content-Type': contentType};
        if (ip !== 'unknown') {
            headers['X-Forwarded-For'] = ip;
        }
        try {
            const response = await fetchImpl(upstreamUrl, {
                method: 'POST',
                headers,
                body,
                signal: AbortSignal.timeout(timeoutMs),
            });
            await response.arrayBuffer();

            return new Response(null, {status: response.ok ? 204 : 502});
        } catch (e) {
            console.error('[csp-report] upstream request failed:', e);

            return new Response(null, {status: 502});
        }
    };
}
