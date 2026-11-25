import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import '../test/memoryStorage';
import {act, cleanup, fireEvent, render, screen} from '@testing-library/react';
import {CookieConsentBanner} from './CookieConsentBanner';
import {
    CONSENT_STORAGE_KEY,
    openCookieConsentBanner,
    readCookieConsent,
    resetCookieConsentForTests,
} from './cookieConsent';
import {initMatomo, resetMatomoForTests} from './matomo';

function init(cookieConsent = true): void {
    initMatomo({baseUrl: 'https://matomo.test', siteId: '3', cookieConsent});
    window._paq = [];
}

function banner(): HTMLElement | null {
    return screen.queryByTestId('cookie-consent-banner');
}

beforeEach(() => {
    window.localStorage.clear();
    resetCookieConsentForTests();
    resetMatomoForTests();
    delete window._paq;
});

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

describe('CookieConsentBanner', () => {
    it('asks the visitor without a stored choice', () => {
        init();
        render(<CookieConsentBanner />);

        expect(banner()).not.toBeNull();
        expect(screen.getByRole('region').getAttribute('aria-label')).toBe(
            'Cookie consent'
        );
    });

    it('lets Matomo set its cookies once accepted', () => {
        init();
        render(<CookieConsentBanner />);
        fireEvent.click(screen.getByTestId('cookie-consent-accept'));

        expect(banner()).toBeNull();
        expect(readCookieConsent()?.granted).toBe(true);
        expect(window._paq).toEqual([['setCookieConsentGiven']]);
    });

    it('makes Matomo forget its cookies once refused', () => {
        init();
        render(<CookieConsentBanner />);
        fireEvent.click(screen.getByTestId('cookie-consent-refuse'));

        expect(banner()).toBeNull();
        expect(readCookieConsent()?.granted).toBe(false);
        expect(window._paq).toEqual([['forgetCookieConsentGiven']]);
    });

    it('is hidden with a valid choice, until reopened', () => {
        window.localStorage.setItem(
            CONSENT_STORAGE_KEY,
            JSON.stringify({granted: false, at: new Date().toISOString()})
        );
        init();
        render(<CookieConsentBanner />);

        expect(banner()).toBeNull();

        act(() => openCookieConsentBanner());
        expect(banner()).not.toBeNull();
    });

    it('is never shown in a frame', () => {
        vi.stubGlobal('top', {});
        init();
        render(<CookieConsentBanner />);

        expect(banner()).toBeNull();

        act(() => openCookieConsentBanner());
        expect(banner()).toBeNull();
    });

    it('is never shown when the consent is not required', () => {
        init(false);
        render(<CookieConsentBanner />);
        act(() => openCookieConsentBanner());

        expect(banner()).toBeNull();
    });
});
