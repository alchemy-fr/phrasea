import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import '../test/memoryStorage';
import {act, cleanup, render} from '@testing-library/react';
import {MatomoTracker} from './MatomoTracker';
import {
    CONSENT_STORAGE_KEY,
    chooseCookieConsent,
    resetCookieConsentForTests,
} from './cookieConsent';
import {resetMatomoForTests, type MatomoConfig} from './matomo';

vi.mock('next/navigation', () => ({
    usePathname: () => '/assets',
    useSearchParams: () => new URLSearchParams(),
}));

const matomo: MatomoConfig = {
    baseUrl: 'https://matomo.test',
    siteId: '3',
    cookieConsent: true,
};

function storeConsent(granted: boolean): void {
    window.localStorage.setItem(
        CONSENT_STORAGE_KEY,
        JSON.stringify({granted, at: new Date().toISOString()})
    );
}

/** The user id instructions given to Matomo */
function userIds(): unknown[][] {
    return (window._paq ?? []).filter(
        ([name]) => name === 'setUserId' || name === 'resetUserId'
    );
}

function renderTracker(config: MatomoConfig = matomo): void {
    render(<MatomoTracker matomo={config} userId="u1" authResolved />);
}

beforeEach(() => {
    window.localStorage.clear();
    resetCookieConsentForTests();
    resetMatomoForTests();
    delete window._paq;
});

afterEach(() => {
    cleanup();
});

describe('MatomoTracker: user id', () => {
    it('is not sent before the visitor chooses', () => {
        renderTracker();

        expect(userIds()).toEqual([['resetUserId']]);
    });

    it('is not sent once the cookies are refused', () => {
        storeConsent(false);
        renderTracker();

        expect(userIds()).toEqual([['resetUserId']]);
    });

    it('is sent once the cookies are accepted', () => {
        storeConsent(true);
        renderTracker();

        expect(userIds()).toEqual([['setUserId', 'u1']]);
    });

    it('follows the choice of the visitor', () => {
        renderTracker();

        act(() => chooseCookieConsent(true));
        expect(userIds()).toEqual([['resetUserId'], ['setUserId', 'u1']]);

        act(() => chooseCookieConsent(false));
        expect(userIds()).toEqual([
            ['resetUserId'],
            ['setUserId', 'u1'],
            ['resetUserId'],
        ]);
    });

    it('is sent when the visitor is not asked', () => {
        renderTracker({...matomo, cookieConsent: false});

        expect(userIds()).toEqual([['setUserId', 'u1']]);
    });
});
