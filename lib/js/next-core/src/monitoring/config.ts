import {parseDsn} from './tunnelDsn';

/**
 * What the browser needs to report its errors to Sentry. Never the DSN: the
 * browser SDK uses `SENTRY_TUNNEL_DSN`, the tunnel puts the real one back.
 */
export type SentryClientConfig = {
    environment?: string;
    release?: string;
};

/**
 * The Sentry configuration of the browser from `SENTRY_ENVIRONMENT` and
 * `SENTRY_RELEASE`; undefined without a valid `SENTRY_DSN`, the errors are
 * then not reported (the tunnel is disabled too).
 */
export function sentryClientConfigFromEnv(
    env: Record<string, string | undefined>
): SentryClientConfig | undefined {
    return env.SENTRY_DSN && parseDsn(env.SENTRY_DSN)
        ? {
              environment: env.SENTRY_ENVIRONMENT,
              release: env.SENTRY_RELEASE,
          }
        : undefined;
}
