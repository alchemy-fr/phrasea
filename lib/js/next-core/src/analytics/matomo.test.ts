import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import '../test/memoryStorage';
import {
    grantCookieConsent,
    initMatomo,
    isCookieConsentRequired,
    isMediaTrackingEnabled,
    resetMatomoForTests,
    revokeCookieConsent,
    scanForMedia,
    setUserId,
    trackContentImpression,
    trackContentInteraction,
    trackEvent,
    trackPageView,
    trackSiteSearch,
} from './matomo';
import {CONSENT_STORAGE_KEY} from './cookieConsent';

function scripts(): HTMLScriptElement[] {
    return Array.from(
        document.querySelectorAll<HTMLScriptElement>('script#matomo-tracker')
    );
}

beforeEach(() => {
    resetMatomoForTests();
    window.localStorage.clear();
    delete window._paq;
    scripts().forEach(s => s.remove());
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('matomo', () => {
    it('does nothing before the initialization', () => {
        trackPageView('/assets', 'Assets');
        trackEvent('Asset', 'download');
        trackContentImpression('a.jpg', 't1');
        setUserId('u1');
        grantCookieConsent();
        revokeCookieConsent();

        expect(window._paq).toBeUndefined();
    });

    it('does nothing without window', () => {
        vi.stubGlobal('window', undefined);
        initMatomo({baseUrl: 'https://matomo.test', siteId: '3'});
        trackEvent('Asset', 'download');
        vi.unstubAllGlobals();

        expect(window._paq).toBeUndefined();
        expect(scripts()).toHaveLength(0);
    });

    it('ignores an invalid site id', () => {
        initMatomo({baseUrl: 'https://matomo.test', siteId: 'abc'});
        trackEvent('Asset', 'download');

        expect(window._paq).toBeUndefined();
    });

    it('configures the tracker', () => {
        initMatomo({baseUrl: 'https://matomo.test', siteId: '3'});

        expect(window._paq).toEqual([
            ['setTrackerUrl', 'https://matomo.test/matomo.php'],
            ['setSiteId', 3],
            ['setSecureCookie', true],
            ['enableLinkTracking'],
        ]);
        expect(scripts().map(s => s.src)).toEqual([
            'https://matomo.test/matomo.js',
        ]);
    });

    it('disables cookies when asked to', () => {
        initMatomo({
            baseUrl: 'https://matomo.test/',
            siteId: '3',
            disableCookies: true,
        });

        expect(window._paq).toEqual([
            ['setTrackerUrl', 'https://matomo.test/matomo.php'],
            ['setSiteId', 3],
            ['setSecureCookie', true],
            ['disableCookies'],
            ['enableLinkTracking'],
        ]);
        expect(scripts().map(s => s.src)).toEqual([
            'https://matomo.test/matomo.js',
        ]);
    });

    it('asks for the cookie consent before any hit', () => {
        initMatomo({
            baseUrl: 'https://matomo.test',
            siteId: '3',
            cookieConsent: true,
        });
        trackPageView('/assets');

        expect(isCookieConsentRequired()).toBe(true);
        expect(window._paq).toEqual([
            ['setTrackerUrl', 'https://matomo.test/matomo.php'],
            ['setSiteId', 3],
            ['setSecureCookie', true],
            ['requireCookieConsent'],
            ['enableLinkTracking'],
            ['setCustomUrl', '/assets'],
            ['trackPageView'],
        ]);
    });

    it('applies the stored cookie consent', () => {
        window.localStorage.setItem(
            CONSENT_STORAGE_KEY,
            JSON.stringify({granted: true, at: new Date().toISOString()})
        );
        initMatomo({
            baseUrl: 'https://matomo.test',
            siteId: '3',
            cookieConsent: true,
        });

        expect(window._paq?.slice(3, 5)).toEqual([
            ['requireCookieConsent'],
            ['setCookieConsentGiven'],
        ]);
    });

    it('does not ask for a consent when cookies are disabled', () => {
        initMatomo({
            baseUrl: 'https://matomo.test',
            siteId: '3',
            cookieConsent: true,
            disableCookies: true,
        });

        expect(isCookieConsentRequired()).toBe(false);
        expect(window._paq?.map(([name]) => name)).toEqual([
            'setTrackerUrl',
            'setSiteId',
            'setSecureCookie',
            'disableCookies',
            'enableLinkTracking',
        ]);
    });

    it('is initialized once', () => {
        initMatomo({baseUrl: 'https://matomo.test', siteId: '3'});
        initMatomo({baseUrl: 'https://other.test', siteId: '4'});
        resetMatomoForTests();
        initMatomo({baseUrl: 'https://matomo.test', siteId: '3'});

        expect(scripts()).toHaveLength(1);
        expect(window._paq?.filter(([name]) => name === 'setSiteId')).toEqual([
            ['setSiteId', 3],
            ['setSiteId', 3],
        ]);
    });

    describe('once initialized', () => {
        beforeEach(() => {
            initMatomo({baseUrl: 'https://matomo.test', siteId: '3'});
            window._paq = [];
        });

        it('tracks a page view', () => {
            trackPageView('/assets?q=1', 'Assets', '/collections');

            expect(window._paq).toEqual([
                ['setReferrerUrl', '/collections'],
                ['setCustomUrl', '/assets?q=1'],
                ['setDocumentTitle', 'Assets'],
                ['trackPageView'],
            ]);
        });

        it('tracks content', () => {
            trackContentImpression('photo.jpg', 'tracking-1');
            trackContentInteraction('click', 'photo.jpg', 'tracking-1');

            expect(window._paq).toEqual([
                ['trackContentImpression', 'photo.jpg', 'tracking-1'],
                ['trackContentInteraction', 'click', 'photo.jpg', 'tracking-1'],
            ]);
        });

        it('tracks events and site searches', () => {
            trackEvent('Asset', 'download');
            trackEvent('Asset action', 'share', 'menu', 2);
            trackSiteSearch('cat', 'filters', 12);
            trackSiteSearch('dog');

            expect(window._paq).toEqual([
                ['trackEvent', 'Asset', 'download'],
                ['trackEvent', 'Asset action', 'share', 'menu', 2],
                ['trackSiteSearch', 'cat', 'filters', 12],
                ['trackSiteSearch', 'dog', false, false],
            ]);
        });

        it('grants and revokes the cookie consent', () => {
            grantCookieConsent();
            revokeCookieConsent();

            expect(isCookieConsentRequired()).toBe(false);
            expect(window._paq).toEqual([
                ['setCookieConsentGiven'],
                ['forgetCookieConsentGiven'],
            ]);
        });

        it('sets and resets the user id', () => {
            setUserId('u1');
            setUserId(undefined);

            expect(window._paq).toEqual([['setUserId', 'u1'], ['resetUserId']]);
        });

        it('scans media only with the MediaAnalytics plugin', () => {
            const container = document.createElement('div');
            scanForMedia(container);
            expect(window._paq).toEqual([]);
            expect(isMediaTrackingEnabled()).toBe(false);

            resetMatomoForTests();
            initMatomo({
                baseUrl: 'https://matomo.test',
                siteId: '3',
                mediaPluginEnabled: true,
            });
            window._paq = [];
            scanForMedia(container);

            expect(isMediaTrackingEnabled()).toBe(true);
            expect(window._paq).toEqual([
                ['MediaAnalytics::setPingInterval', 10],
                ['MediaAnalytics::scanForMedia', container],
            ]);
        });
    });
});
