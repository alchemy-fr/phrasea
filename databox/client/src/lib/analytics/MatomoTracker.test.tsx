import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {act, render} from '@testing-library/react';

const navigation = vi.hoisted(() => ({
    pathname: '/assets',
    search: '',
}));
vi.mock('next/navigation', () => ({
    usePathname: () => navigation.pathname,
    useSearchParams: () => new URLSearchParams(navigation.search),
}));

type Matomo = {
    baseUrl: string;
    siteId: string;
    mediaPluginEnabled: boolean;
    disableCookies: boolean;
};
const config = vi.hoisted(() => ({
    analytics: {} as {matomo?: Matomo},
}));
vi.mock('@/lib/config/ConfigProvider', () => ({
    useConfig: () => config,
}));

const auth = vi.hoisted(() => ({
    status: 'loading' as 'loading' | 'anonymous' | 'authenticated',
    user: undefined as {id: string} | undefined,
}));
vi.mock('@/lib/auth/AuthProvider', () => ({
    useAuth: () => auth,
}));

const matomo = vi.hoisted(() => ({
    initMatomo: vi.fn(),
    setUserId: vi.fn(),
    trackPageView: vi.fn(),
}));
vi.mock('./matomo', () => matomo);

const {MatomoTracker, getTrackedPath} = await import('./MatomoTracker');

const origin = window.location.origin;

function renderTracker() {
    const view = render(<MatomoTracker />);
    act(() => {
        vi.runAllTimers();
    });

    return {
        rerender: () => {
            view.rerender(<MatomoTracker />);
            act(() => {
                vi.runAllTimers();
            });
        },
    };
}

beforeEach(() => {
    vi.useFakeTimers();
    navigation.pathname = '/assets';
    navigation.search = '';
    auth.status = 'authenticated';
    auth.user = {id: 'u1'};
    config.analytics = {
        matomo: {
            baseUrl: 'https://matomo.test',
            siteId: '3',
            mediaPluginEnabled: false,
            disableCookies: false,
        },
    };
    document.title = 'Databox';
});

afterEach(() => {
    vi.useRealTimers();
    vi.clearAllMocks();
});

describe('MatomoTracker', () => {
    it('tracks nothing without Matomo', () => {
        config.analytics = {};
        renderTracker();

        expect(matomo.initMatomo).not.toHaveBeenCalled();
        expect(matomo.setUserId).not.toHaveBeenCalled();
        expect(matomo.trackPageView).not.toHaveBeenCalled();
    });

    it('tracks a page view on every navigation', () => {
        const {rerender} = renderTracker();

        expect(matomo.initMatomo).toHaveBeenCalledWith(config.analytics.matomo);
        expect(matomo.trackPageView).toHaveBeenCalledWith(
            `${origin}/assets`,
            'Databox',
            undefined
        );

        navigation.search = 'q=cat';
        document.title = 'Search';
        rerender();
        expect(matomo.trackPageView).toHaveBeenLastCalledWith(
            `${origin}/assets?q=cat`,
            'Search',
            `${origin}/assets`
        );

        navigation.pathname = '/collections';
        navigation.search = '';
        rerender();
        expect(matomo.trackPageView).toHaveBeenLastCalledWith(
            `${origin}/collections`,
            'Search',
            `${origin}/assets?q=cat`
        );

        rerender();
        expect(matomo.trackPageView).toHaveBeenCalledTimes(3);
    });

    it('waits for the session before tracking', () => {
        auth.status = 'loading';
        auth.user = undefined;
        const {rerender} = renderTracker();

        expect(matomo.trackPageView).not.toHaveBeenCalled();
        expect(matomo.setUserId).not.toHaveBeenCalled();

        auth.status = 'authenticated';
        auth.user = {id: 'u1'};
        rerender();

        expect(matomo.setUserId).toHaveBeenCalledWith('u1');
        expect(matomo.trackPageView).toHaveBeenCalledTimes(1);
        expect(matomo.setUserId.mock.invocationCallOrder[0]).toBeLessThan(
            matomo.trackPageView.mock.invocationCallOrder[0]
        );
    });

    it('sets the user id on login and resets it on logout', () => {
        auth.status = 'anonymous';
        auth.user = undefined;
        const {rerender} = renderTracker();
        expect(matomo.setUserId).toHaveBeenLastCalledWith(undefined);

        auth.status = 'authenticated';
        auth.user = {id: 'u1'};
        rerender();
        expect(matomo.setUserId).toHaveBeenLastCalledWith('u1');

        auth.status = 'anonymous';
        auth.user = undefined;
        rerender();
        expect(matomo.setUserId).toHaveBeenLastCalledWith(undefined);
        expect(matomo.setUserId).toHaveBeenCalledTimes(3);
        expect(matomo.trackPageView).toHaveBeenCalledTimes(1);
    });
});

describe('getTrackedPath', () => {
    it('skips the OAuth callback', () => {
        expect(getTrackedPath('/auth/callback', 'code=x')).toBeUndefined();
    });

    it('hides the share token', () => {
        expect(getTrackedPath('/s/abc/secret', '')).toBe('/s/abc/_token_');
        expect(getTrackedPath('/s/abc/secret/asset-1', '')).toBe(
            '/s/abc/_token_/asset-1'
        );
    });

    it('keeps the query', () => {
        expect(getTrackedPath('/assets', 'q=cat')).toBe('/assets?q=cat');
    });
});
