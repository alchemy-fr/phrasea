/**
 * Same origin route relaying the envelopes of the browser SDK to Sentry.
 * Ad blockers drop the requests whose path mentions `sentry`, `monitoring` or
 * `telemetry`.
 */
export const SENTRY_TUNNEL_PATH = '/api/relay';
