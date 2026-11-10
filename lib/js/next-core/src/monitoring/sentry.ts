/**
 * Thin wrapper around the Sentry browser SDK.
 *
 * Only errors are reported (neither tracing nor replay), through the same
 * origin tunnel (`SENTRY_TUNNEL_PATH`) which relays them to the Sentry
 * project of the server DSN behind a rate limit.
 *
 * Every function is a no-op on the server and until `initSentry()` has been
 * called, which only happens when the stack configures Sentry: callers never
 * need to check whether monitoring is enabled.
 */
import * as Sentry from '@sentry/react';
import {SENTRY_TUNNEL_PATH} from './tunnelPath';

export {SENTRY_TUNNEL_PATH};

export type SentryClientConfig = {
    dsn: string;
    environment?: string;
    release?: string;
};

export type SentryUserInfo = {
    id: string;
    username?: string;
    email?: string;
};

let initialized = false;

export function isSentryEnabled(): boolean {
    return initialized;
}

export function initSentry(
    config: SentryClientConfig,
    {appId, appName}: {appId: string; appName: string}
): void {
    if (initialized || typeof window === 'undefined' || !config.dsn) {
        return;
    }
    initialized = true;

    Sentry.init({
        dsn: config.dsn,
        environment: config.environment,
        release: config.release,
        tunnel: SENTRY_TUNNEL_PATH,
        // The user is set explicitly (`setSentryUser`): no IP address is
        // inferred from the requests
        dataCollection: {userInfo: false},
        ignoreErrors: [
            /Loading (CSS )?chunk \d+ failed/i,
            /^Network Error$/i,
            /^Request failed with status code/i,
            /^Request aborted$/i,
            /^Failed to fetch$/i,
            /^NetworkError when attempting to fetch resource/i,
            /^Load failed$/i,
            /^Non-Error promise rejection captured with value: Object Not Found/i,
            /zaloJSV2/i,
            /property 'javaEnabled' is a read-only/i,
            /^timeout exceeded$/i,
        ],
        denyUrls: [
            /webkit-masked-url/i,
            /safari-web-extension/i,
            // Chrome extensions
            /extensions\//i,
            /^chrome:\/\//i,
            // Facebook flakiness
            /graph\.facebook\.com/i,
        ],
    });

    Sentry.setTag('app.name', appName);
    Sentry.setTag('app.id', appId);
}

/** The signed-in user, `undefined` once signed out */
export function setSentryUser(user: SentryUserInfo | undefined): void {
    if (!initialized) {
        return;
    }
    Sentry.setUser(
        user ? {id: user.id, username: user.username, email: user.email} : null
    );
}

export function captureException(error: unknown): void {
    if (!initialized) {
        return;
    }
    Sentry.captureException(error);
}

/** Test helper: forgets the initialization */
export function resetSentryForTests(): void {
    initialized = false;
}
