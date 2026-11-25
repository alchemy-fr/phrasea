import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import '../test/memoryStorage';
import {
    CONSENT_STORAGE_KEY,
    chooseCookieConsent,
    getSnapshot,
    isBannerRequested,
    openCookieConsentBanner,
    readCookieConsent,
    resetCookieConsentForTests,
    storeCookieConsent,
    subscribe,
} from './cookieConsent';
import {initMatomo, resetMatomoForTests} from './matomo';

const now = new Date('2026-11-25T12:00:00Z');

function monthsAgo(months: number): string {
    const date = new Date(now);
    date.setMonth(date.getMonth() - months);

    return date.toISOString();
}

function store(value: unknown): void {
    window.localStorage.setItem(CONSENT_STORAGE_KEY, JSON.stringify(value));
}

beforeEach(() => {
    window.localStorage.clear();
    resetCookieConsentForTests();
    resetMatomoForTests();
    delete window._paq;
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('readCookieConsent', () => {
    it('returns nothing without a stored choice', () => {
        expect(readCookieConsent(now)).toBeUndefined();
    });

    it('ignores an invalid value', () => {
        window.localStorage.setItem(CONSENT_STORAGE_KEY, '{not json');
        expect(readCookieConsent(now)).toBeUndefined();

        store({granted: 'yes', at: monthsAgo(1)});
        expect(readCookieConsent(now)).toBeUndefined();

        store({granted: true, at: 'yesterday'});
        expect(readCookieConsent(now)).toBeUndefined();
    });

    it('forgets a choice older than 13 months', () => {
        store({granted: true, at: monthsAgo(14)});
        expect(readCookieConsent(now)).toBeUndefined();

        const at = monthsAgo(12);
        store({granted: false, at});
        expect(readCookieConsent(now)).toEqual({granted: false, at});
    });

    it('survives an unavailable storage', () => {
        vi.spyOn(window.localStorage, 'getItem').mockImplementation(() => {
            throw new Error('SecurityError');
        });
        vi.spyOn(window.localStorage, 'setItem').mockImplementation(() => {
            throw new Error('QuotaExceededError');
        });

        expect(readCookieConsent(now)).toBeUndefined();
        expect(storeCookieConsent(true, now)).toEqual({
            granted: true,
            at: now.toISOString(),
        });
        expect(() => chooseCookieConsent(false)).not.toThrow();
        expect(getSnapshot().consent?.granted).toBe(false);
    });
});

describe('cookie consent store', () => {
    it('reads the stored choice once', () => {
        const at = monthsAgo(1);
        store({granted: true, at});

        const snapshot = getSnapshot();
        expect(snapshot).toEqual({
            consent: {granted: true, at},
            bannerRequested: false,
        });
        expect(getSnapshot()).toBe(snapshot);
    });

    it('stores the choice, notifies and closes the banner', () => {
        initMatomo({
            baseUrl: 'https://matomo.test',
            siteId: '3',
            cookieConsent: true,
        });
        window._paq = [];
        const listener = vi.fn();
        const unsubscribe = subscribe(listener);

        openCookieConsentBanner();
        expect(isBannerRequested()).toBe(true);
        expect(listener).toHaveBeenCalledTimes(1);

        chooseCookieConsent(true);

        expect(isBannerRequested()).toBe(false);
        expect(listener).toHaveBeenCalledTimes(2);
        expect(getSnapshot().consent?.granted).toBe(true);
        expect(
            JSON.parse(window.localStorage.getItem(CONSENT_STORAGE_KEY)!)
        ).toMatchObject({granted: true});
        expect(window._paq).toEqual([['setCookieConsentGiven']]);

        unsubscribe();
        chooseCookieConsent(false);

        expect(listener).toHaveBeenCalledTimes(2);
        expect(readCookieConsent()?.granted).toBe(false);
        expect(window._paq).toEqual([
            ['setCookieConsentGiven'],
            ['forgetCookieConsentGiven'],
        ]);
    });
});
