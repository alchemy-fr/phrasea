import 'server-only';
import {
    createSentryTunnelHandler,
    sentryTunnelOptionsFromEnv,
} from './sentryTunnel';

const handler = createSentryTunnelHandler(
    sentryTunnelOptionsFromEnv(process.env)
);

/**
 * `POST` of the `SENTRY_TUNNEL_PATH` route of a client: relays the envelopes
 * of the Sentry browser SDK to the project of `SENTRY_DSN`, behind a rate
 * limit (`SENTRY_TUNNEL_RATE_LIMIT_PER_IP`, `SENTRY_TUNNEL_RATE_LIMIT_GLOBAL`).
 */
export function POST(request: Request): Promise<Response> {
    return handler(request);
}
