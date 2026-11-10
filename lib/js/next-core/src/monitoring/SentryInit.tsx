'use client';

import {useState} from 'react';
import {initSentry, type SentryClientConfig} from './sentry';

/**
 * Starts reporting the browser errors to Sentry when the stack configures it
 * (`sentry`). Renders nothing.
 *
 * Meant to be rendered as early as possible (above the authentication), to
 * catch the errors of the whole page; the initialization happens during the
 * first render, before the effects of the rest of the tree.
 */
export function SentryInit({
    sentry,
    appId,
    appName,
}: {
    sentry: SentryClientConfig | undefined;
    appId: string;
    appName: string;
}) {
    useState(() => {
        if (sentry) {
            initSentry(sentry, {appId, appName});
        }

        return true;
    });

    return null;
}
