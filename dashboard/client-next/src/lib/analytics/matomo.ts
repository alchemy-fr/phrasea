/**
 * Thin wrapper around the Matomo JavaScript tracker (`window._paq`).
 *
 * Every function is a no-op on the server and until `initMatomo()` has been
 * called, which only happens when the stack configures Matomo: callers never
 * need to check whether analytics are enabled.
 */

type Instruction = [string, ...unknown[]];

declare global {
    interface Window {
        _paq?: Instruction[];
    }
}

export type MatomoConfig = {
    baseUrl: string;
    siteId: string;
    disableCookies?: boolean;
};

const SCRIPT_ID = 'matomo-tracker';

let initialized = false;

function push(...instruction: Instruction): void {
    if (!initialized || typeof window === 'undefined') {
        return;
    }
    window._paq = window._paq ?? [];
    window._paq.push(instruction);
}

export function initMatomo(config: MatomoConfig): void {
    if (initialized || typeof window === 'undefined') {
        return;
    }
    const siteId = parseInt(config.siteId, 10);
    if (!config.baseUrl || Number.isNaN(siteId)) {
        return;
    }
    initialized = true;
    const baseUrl = config.baseUrl.replace(/\/+$/, '');

    push('setTrackerUrl', `${baseUrl}/matomo.php`);
    push('setSiteId', siteId);
    push('setSecureCookie', true);
    if (config.disableCookies) {
        push('disableCookies');
    }
    push('enableLinkTracking');

    if (!document.getElementById(SCRIPT_ID)) {
        const script = document.createElement('script');
        script.id = SCRIPT_ID;
        script.async = true;
        script.src = `${baseUrl}/matomo.js`;
        document.head.appendChild(script);
    }
}

export function trackPageView(
    url: string,
    title?: string,
    referrer?: string
): void {
    if (referrer) {
        push('setReferrerUrl', referrer);
    }
    push('setCustomUrl', url);
    if (title) {
        push('setDocumentTitle', title);
    }
    push('trackPageView');
}

export function setUserId(id: string | undefined): void {
    if (id) {
        push('setUserId', id);
    } else {
        push('resetUserId');
    }
}
