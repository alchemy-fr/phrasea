import {parseDsn} from '../monitoring/tunnelDsn';

type Env = Record<string, string | undefined>;

/**
 * Same origin route relaying the violation reports of the browser to the
 * Sentry project of the server (`SENTRY_DSN`), which never leaves the server.
 */
export const CSP_REPORT_PATH = '/api/relay/csp';

/** Request header carrying the nonce from the proxy to the server components */
export const NONCE_HEADER = 'x-nonce';

/** Name of the `Reporting-Endpoints` entry the `report-to` directive targets */
export const CSP_REPORT_GROUP = 'csp';

/**
 * The sources a page needs on top of its own origin, per directive. Every
 * entry is a CSP source expression (`https://host`, `wss://host`,
 * `https://*.host`…); a value may hold several, space separated.
 */
export type CspSources = {
    /**
     * Fallback for the browsers ignoring `'strict-dynamic'`: the others only
     * run the scripts loaded by a script bearing the nonce
     */
    scriptSrc?: (string | undefined)[];
    connectSrc?: (string | undefined)[];
    imgSrc?: (string | undefined)[];
    mediaSrc?: (string | undefined)[];
    fontSrc?: (string | undefined)[];
    frameSrc?: (string | undefined)[];
    workerSrc?: (string | undefined)[];
    formAction?: (string | undefined)[];
    /**
     * The sites allowed to embed the pages, on top of the same origin. No
     * `frame-ancestors` directive when undefined: any site may.
     */
    frameAncestors?: (string | undefined)[];
};

export type CspOptions = CspSources & {
    nonce: string;
    /** `next dev` evaluates the modules it serves (`'unsafe-eval'`) */
    dev?: boolean;
    /** Where the violations are reported (`report-uri` and `report-to`) */
    report?: {uri: string; group: string};
};

/** The origin of a URL, undefined when it is not one */
export function originOf(url: string | undefined): string | undefined {
    if (!url?.trim()) {
        return undefined;
    }
    try {
        const {origin} = new URL(url.trim());

        return origin === 'null' ? undefined : origin;
    } catch {
        return undefined;
    }
}

/** A source list: the values split on white space, deduplicated */
export function sourceList(...values: (string | undefined)[]): string {
    return [
        ...new Set(values.flatMap(v => (v ?? '').split(/\s+/)).filter(Boolean)),
    ].join(' ');
}

/** The sources of several parts of a page, put together */
export function mergeCspSources(...parts: CspSources[]): CspSources {
    const merged: CspSources = {};
    for (const part of parts) {
        for (const [key, values] of Object.entries(part) as [
            keyof CspSources,
            CspSources[keyof CspSources],
        ][]) {
            if (values) {
                merged[key] = [...(merged[key] ?? []), ...values];
            }
        }
    }

    return merged;
}

/**
 * A nonce of 128 random bits, base64 encoded: a new one for every response,
 * or it no longer proves that a script was written by the server.
 */
export function generateNonce(): string {
    const bytes = crypto.getRandomValues(new Uint8Array(16));

    return btoa(String.fromCharCode(...bytes));
}

/**
 * The Content Security Policy of the pages of a Next.js client.
 *
 * Scripts are allowed by nonce only (`'strict-dynamic'`): Next puts the nonce
 * of the request on its own scripts, and the scripts they load (chunks,
 * Matomo…) are trusted in turn; an injected `<script>` or event handler is
 * not. Styles stay allowed inline: Tailwind, Radix and the theme write some.
 */
export function buildCsp({
    nonce,
    dev = false,
    report,
    scriptSrc,
    connectSrc,
    imgSrc,
    mediaSrc,
    fontSrc,
    frameSrc,
    workerSrc,
    formAction,
    frameAncestors,
}: CspOptions): string {
    const self = "'self'";
    const directives: [string, string][] = [
        ['default-src', self],
        [
            'script-src',
            sourceList(
                self,
                `'nonce-${nonce}'`,
                "'strict-dynamic'",
                ...(scriptSrc ?? []),
                dev ? "'unsafe-eval'" : undefined
            ),
        ],
        ['style-src', sourceList(self, "'unsafe-inline'")],
        ['img-src', sourceList(self, 'data:', 'blob:', ...(imgSrc ?? []))],
        ['media-src', sourceList(self, 'data:', 'blob:', ...(mediaSrc ?? []))],
        ['font-src', sourceList(self, 'data:', ...(fontSrc ?? []))],
        ['connect-src', sourceList(self, ...(connectSrc ?? []))],
        ['frame-src', sourceList(self, ...(frameSrc ?? []))],
        // pdf.js and Mapbox run in workers, Mapbox's from a blob
        ['worker-src', sourceList(self, 'blob:', ...(workerSrc ?? []))],
        ['object-src', "'none'"],
        ['base-uri', self],
        ['form-action', sourceList(self, ...(formAction ?? []))],
    ];
    if (frameAncestors) {
        directives.push([
            'frame-ancestors',
            sourceList(self, ...frameAncestors),
        ]);
    }
    if (report) {
        directives.push(
            ['report-uri', report.uri],
            ['report-to', report.group]
        );
    }

    return directives.map(([name, value]) => `${name} ${value}`).join('; ');
}

/** The given directives of a policy, in their order */
export function pickDirectives(policy: string, names: string[]): string {
    return policy
        .split(';')
        .map(d => d.trim())
        .filter(d => names.includes(d.split(/\s+/)[0]))
        .join('; ');
}

/**
 * Whether the policy is only reported (`Content-Security-Policy-Report-Only`)
 * rather than enforced: unless `CSP_REPORT_ONLY` is false.
 */
export function isCspReportOnly(env: Env): boolean {
    return !['0', 'false', 'off', 'n', 'no'].includes(
        (env.CSP_REPORT_ONLY ?? '').trim().toLowerCase()
    );
}

/** Violations are reported to Sentry when `SENTRY_DSN` is valid */
export function cspReportFromEnv(env: Env): CspOptions['report'] {
    return env.SENTRY_DSN && parseDsn(env.SENTRY_DSN)
        ? {uri: CSP_REPORT_PATH, group: CSP_REPORT_GROUP}
        : undefined;
}

/**
 * The sources of the services every client of the stack talks to: Keycloak
 * (session iframe, token requests, sign-in forms), the buckets serving the
 * files (`CONFIGURATOR_S3_ENDPOINT`, its virtual-hosted buckets unless
 * `CONFIGURATOR_S3_USE_PATH_STYLE_ENDPOINT`, `CLOUD_FRONT_URL`), Matomo, and
 * `CSP_EXTRA_SOURCES` (space separated) for whatever else serves files to the
 * pages (another CDN, an external logo…).
 */
export function stackCspSources(env: Env): CspSources {
    const keycloak = originOf(env.KEYCLOAK_URL);
    const matomo = originOf(env.MATOMO_URL);
    const s3 = originOf(env.CONFIGURATOR_S3_ENDPOINT);
    const pathStyle = ['1', 'true', 'on', 'y', 'yes'].includes(
        (env.CONFIGURATOR_S3_USE_PATH_STYLE_ENDPOINT ?? '').toLowerCase()
    );
    const buckets = s3 && !pathStyle ? s3.replace('://', '://*.') : undefined;
    const files = [s3, buckets, originOf(env.CLOUD_FRONT_URL)];
    const extra = env.CSP_EXTRA_SOURCES;

    return {
        scriptSrc: [matomo],
        connectSrc: [keycloak, matomo, ...files, extra],
        imgSrc: [matomo, ...files, extra],
        mediaSrc: [...files, extra],
        frameSrc: [keycloak],
        formAction: [keycloak],
    };
}
