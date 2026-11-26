/**
 * DSN given to the browser SDK instead of the real one, which never leaves
 * the server: the tunnel (`SENTRY_TUNNEL_PATH`) replaces it with the DSN of
 * the server in the envelopes it relays. Valid for the SDK (public key,
 * numeric project) but never resolvable (`.invalid`).
 */
export const SENTRY_TUNNEL_DSN = 'https://public@sentry-tunnel.invalid/0';

export type ParsedDsn = {
    /** With the trailing colon, as `URL.protocol` */
    protocol: string;
    /** With the port, if any */
    host: string;
    /** Path under which Sentry is served, empty or starting with a slash */
    pathPrefix: string;
    projectId: string;
};

/** `https://<key>@<host>[/<path>]/<projectId>`, undefined when invalid */
export function parseDsn(dsn: string): ParsedDsn | undefined {
    let url: URL;
    try {
        url = new URL(dsn);
    } catch {
        return undefined;
    }
    if (!['http:', 'https:'].includes(url.protocol) || !url.username) {
        return undefined;
    }
    const segments = url.pathname.replace(/\/+$/, '').split('/');
    const projectId = segments.pop() ?? '';
    if (!/^\d+$/.test(projectId)) {
        return undefined;
    }

    return {
        protocol: url.protocol,
        host: url.host,
        pathPrefix: segments.join('/'),
        projectId,
    };
}
