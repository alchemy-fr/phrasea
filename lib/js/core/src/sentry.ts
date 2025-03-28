import * as Sentry from '@sentry/react';
import {CaptureContext} from '@sentry/types';
import {SentryConfig} from './types';

export function initSentry({
    sentryDsn,
    sentryEnvironment,
    sentryRelease,
    appId,
    appName,
}: SentryConfig) {
    Sentry.init({
        enabled: !!sentryDsn,
        dsn: sentryDsn,
        environment: sentryEnvironment,
        release: sentryRelease,
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
        tracesSampler: samplingContext => {
            if (samplingContext.location?.host.startsWith('profile')) {
                return 0.2;
            }

            return 0.01;
        },
    });

    Sentry.setTag('app.name', appName);
    Sentry.setTag('app.id', appId);
}

export function setSentryUser(
    user:
        | {
              id: string;
              username: string;
          }
        | undefined
) {
    console.log('setSentryUser', user);
    Sentry.setUser(
        user
            ? {
                  id: user.id,
                  email: user.username,
              }
            : null
    );
}

export function logError(error: any, captureContext?: CaptureContext): void {
    console.error(error);
    Sentry.captureException(error, captureContext);
}
