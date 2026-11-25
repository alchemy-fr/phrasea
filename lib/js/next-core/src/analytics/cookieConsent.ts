/**
 * The visitor's choice about the Matomo cookies, kept in the local storage of
 * the origin (each client has its own), and the store the consent banner
 * reads.
 *
 * Matomo's own `mtm_cookie_consent` cookie does not remember a refusal: the
 * choice stored here is the only one that counts, and Matomo is told about it
 * on every page load. A choice older than 13 months (CNIL recommendation) is
 * forgotten, so that the visitor is asked again.
 */

import {grantCookieConsent, revokeCookieConsent} from './matomo';

export const CONSENT_STORAGE_KEY = 'phrasea.analytics.cookie-consent';
export const CONSENT_MAX_AGE_DAYS = 395;

const DAY = 24 * 60 * 60 * 1000;

export type CookieConsent = {
    granted: boolean;
    /** ISO date of the choice */
    at: string;
};

export type CookieConsentState = {
    /** The valid choice of the visitor, if any */
    consent: CookieConsent | undefined;
    /** The visitor asked to review the choice (settings menu) */
    bannerRequested: boolean;
};

/**
 * The stored choice, or undefined when there is none, it is unreadable or
 * too old, or the storage is not available (private browsing, blocked).
 */
export function readCookieConsent(
    now: Date = new Date()
): CookieConsent | undefined {
    try {
        const raw = window.localStorage.getItem(CONSENT_STORAGE_KEY);
        if (!raw) {
            return undefined;
        }
        const value: unknown = JSON.parse(raw);
        if (
            !value ||
            typeof value !== 'object' ||
            !('granted' in value) ||
            typeof value.granted !== 'boolean' ||
            !('at' in value) ||
            typeof value.at !== 'string'
        ) {
            return undefined;
        }
        const at = Date.parse(value.at);
        if (
            Number.isNaN(at) ||
            now.getTime() - at > CONSENT_MAX_AGE_DAYS * DAY
        ) {
            return undefined;
        }

        return {granted: value.granted, at: value.at};
    } catch {
        return undefined;
    }
}

/**
 * Stores the choice and returns it. It still holds for the current page when
 * the storage is not available.
 */
export function storeCookieConsent(
    granted: boolean,
    now: Date = new Date()
): CookieConsent {
    const consent: CookieConsent = {granted, at: now.toISOString()};
    try {
        window.localStorage.setItem(
            CONSENT_STORAGE_KEY,
            JSON.stringify(consent)
        );
    } catch {
        // Not remembered: the visitor will be asked again on the next visit
    }

    return consent;
}

let state: CookieConsentState | undefined;
const listeners = new Set<() => void>();

function update(patch: Partial<CookieConsentState>): void {
    state = {...getSnapshot(), ...patch};
    listeners.forEach(listener => listener());
}

/** For `useSyncExternalStore` */
export function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

/**
 * The current state, read from the storage once then kept: the same object
 * is returned until it changes (`useSyncExternalStore`).
 */
export function getSnapshot(): CookieConsentState {
    if (!state) {
        state = {consent: readCookieConsent(), bannerRequested: false};
    }

    return state;
}

/** Shows the banner again, for the visitor to change the choice */
export function openCookieConsentBanner(): void {
    update({bannerRequested: true});
}

export function isBannerRequested(): boolean {
    return getSnapshot().bannerRequested;
}

/** Stores the choice, applies it to Matomo and closes the banner */
export function chooseCookieConsent(granted: boolean): void {
    const consent = storeCookieConsent(granted);
    if (granted) {
        grantCookieConsent();
    } else {
        revokeCookieConsent();
    }
    update({consent, bannerRequested: false});
}

/** Test helper: forgets the state read from the storage */
export function resetCookieConsentForTests(): void {
    state = undefined;
}
