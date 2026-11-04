'use client';

import type {TicketClientError, TicketPageContext} from './types';

const maxErrors = 10;
const maxStackLength = 2_000;

const recentErrors: TicketClientError[] = [];
let collectorInstalled = false;

function push(error: TicketClientError): void {
    recentErrors.push(error);
    if (recentErrors.length > maxErrors) {
        recentErrors.splice(0, recentErrors.length - maxErrors);
    }
}

/**
 * Keeps the last uncaught errors of the session around so that they can be
 * attached to a ticket. Installed once, only when ticketing is enabled.
 */
export function installClientErrorCollector(): () => void {
    if (collectorInstalled || typeof window === 'undefined') {
        return () => undefined;
    }
    collectorInstalled = true;

    const onError = (e: ErrorEvent) => {
        push({
            at: new Date().toISOString(),
            message: e.message || String(e.error ?? 'Error'),
            source: e.filename
                ? `${e.filename}:${e.lineno}:${e.colno}`
                : undefined,
            stack: e.error?.stack?.slice(0, maxStackLength),
        });
    };

    const onRejection = (e: PromiseRejectionEvent) => {
        const reason: any = e.reason;
        push({
            at: new Date().toISOString(),
            message: `Unhandled rejection: ${
                reason?.message ?? String(reason)
            }`,
            stack:
                typeof reason?.stack === 'string'
                    ? reason.stack.slice(0, maxStackLength)
                    : undefined,
        });
    };

    window.addEventListener('error', onError);
    window.addEventListener('unhandledrejection', onRejection);

    return () => {
        window.removeEventListener('error', onError);
        window.removeEventListener('unhandledrejection', onRejection);
        collectorInstalled = false;
    };
}

export function getRecentErrors(): TicketClientError[] {
    return [...recentErrors];
}

export function clearRecentErrors(): void {
    recentErrors.length = 0;
}

export type PageContextExtra = {
    locale?: string;
    dataLocale?: string;
    theme?: string;
};

/**
 * Snapshots the page the user is currently looking at.
 */
export function collectPageContext(
    extra: PageContextExtra = {}
): TicketPageContext {
    const errors = getRecentErrors();

    return {
        url: window.location.href,
        path: `${window.location.pathname}${window.location.search}${window.location.hash}`,
        title: document.title,
        referrer: document.referrer || undefined,
        locale: extra.locale,
        dataLocale: extra.dataLocale,
        theme: extra.theme,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        viewport: {
            width: window.innerWidth,
            height: window.innerHeight,
        },
        screen: {
            width: window.screen.width,
            height: window.screen.height,
            pixelRatio: window.devicePixelRatio,
        },
        userAgent: navigator.userAgent,
        capturedAt: new Date().toISOString(),
        errors: errors.length > 0 ? errors : undefined,
    };
}
