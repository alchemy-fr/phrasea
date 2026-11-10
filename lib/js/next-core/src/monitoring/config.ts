/** What the browser needs to report its errors to Sentry */
export type SentryClientConfig = {
    dsn: string;
    environment?: string;
    release?: string;
};

/**
 * The Sentry configuration of the browser from `SENTRY_DSN`,
 * `SENTRY_ENVIRONMENT` and `SENTRY_RELEASE`; undefined without DSN, the
 * errors are then not reported.
 */
export function sentryClientConfigFromEnv(
    env: Record<string, string | undefined>
): SentryClientConfig | undefined {
    return env.SENTRY_DSN
        ? {
              dsn: env.SENTRY_DSN,
              environment: env.SENTRY_ENVIRONMENT,
              release: env.SENTRY_RELEASE,
          }
        : undefined;
}
