/**
 * Thin wrapper around the Matomo JavaScript tracker (`window._paq`).
 *
 * Every function is a no-op on the server and until `initMatomo()` has been
 * called, which only happens when the stack configures Matomo: callers never
 * need to check whether analytics are enabled.
 *
 * The content piece and the media resource sent here are read back by the
 * API (`MatomoManager`) with `filter_pattern=<trackingId>`: they must be the
 * asset's `resolvedTrackingId`, as is.
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
    /** The MediaAnalytics plugin is installed on the Matomo instance */
    mediaPluginEnabled?: boolean;
    disableCookies?: boolean;
};

const SCRIPT_ID = 'matomo-tracker';
const MEDIA_PING_INTERVAL = 10;

let initialized = false;
let mediaTracking = false;

function push(...instruction: Instruction): void {
    if (!initialized || typeof window === 'undefined') {
        return;
    }
    window._paq = window._paq ?? [];
    window._paq.push(instruction);
}

export function isMatomoEnabled(): boolean {
    return initialized;
}

export function isMediaTrackingEnabled(): boolean {
    return initialized && mediaTracking;
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
    mediaTracking = Boolean(config.mediaPluginEnabled);
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

export function trackEvent(
    category: string,
    action: string,
    name?: string,
    value?: number
): void {
    const instruction: Instruction = ['trackEvent', category, action];
    if (name !== undefined || value !== undefined) {
        instruction.push(name);
    }
    if (value !== undefined) {
        instruction.push(value);
    }
    push(...instruction);
}

export function trackSiteSearch(
    keyword: string,
    category?: string,
    count?: number
): void {
    push('trackSiteSearch', keyword, category ?? false, count ?? false);
}

export function trackContentImpression(name: string, piece: string): void {
    push('trackContentImpression', name, piece);
}

export function trackContentInteraction(
    interaction: string,
    name: string,
    piece: string
): void {
    push('trackContentInteraction', interaction, name, piece);
}

export function setUserId(id: string | undefined): void {
    if (id) {
        push('setUserId', id);
    } else {
        push('resetUserId');
    }
}

/**
 * Lets the MediaAnalytics plugin pick up the `<video>` elements of `container`
 * carrying `data-matomo-resource` / `data-matomo-title`.
 */
export function scanForMedia(container: HTMLElement): void {
    if (!mediaTracking) {
        return;
    }
    push('MediaAnalytics::setPingInterval', MEDIA_PING_INTERVAL);
    push('MediaAnalytics::scanForMedia', container);
}

/** Test helper: forgets the initialization (the script tag is kept). */
export function resetMatomoForTests(): void {
    initialized = false;
    mediaTracking = false;
}
