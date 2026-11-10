import {
    createSentryTunnelHandler,
    sentryTunnelOptionsFromEnv,
} from '@alchemy/next-core/server/sentryTunnel';

export const dynamic = 'force-dynamic';

const handler = createSentryTunnelHandler(
    sentryTunnelOptionsFromEnv(process.env)
);

/**
 * Relays the envelopes of the Sentry browser SDK to the project of
 * `SENTRY_DSN`, behind a rate limit (`SENTRY_TUNNEL_RATE_LIMIT_PER_IP`,
 * `SENTRY_TUNNEL_RATE_LIMIT_GLOBAL`).
 */
export function POST(request: Request): Promise<Response> {
    return handler(request);
}
