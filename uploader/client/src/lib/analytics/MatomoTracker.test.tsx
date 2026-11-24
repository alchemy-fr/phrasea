import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {act, render} from '@testing-library/react';

const navigation = vi.hoisted(() => ({
    pathname: '/',
    search: '',
}));
vi.mock('next/navigation', () => ({
    usePathname: () => navigation.pathname,
    useSearchParams: () => new URLSearchParams(navigation.search),
}));

vi.mock('@/lib/config/ConfigProvider', () => ({
    useConfig: () => ({
        analytics: {matomo: {baseUrl: 'https://matomo.test', siteId: '3'}},
    }),
}));

vi.mock('@/lib/auth/AuthProvider', () => ({
    useAuth: () => ({status: 'anonymous', user: undefined}),
}));

const matomo = vi.hoisted(() => ({
    initMatomo: vi.fn(),
    setUserId: vi.fn(),
    trackPageView: vi.fn(),
}));
vi.mock('@alchemy/next-core/analytics/matomo', () => matomo);

const {MatomoTracker} = await import('./MatomoTracker');

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
    navigation.pathname = '/';
    navigation.search = '';
    document.title = 'Uploader';
});

afterEach(() => {
    vi.useRealTimers();
    vi.clearAllMocks();
});

describe('MatomoTracker', () => {
    it('never sends the token of an upload link', () => {
        navigation.pathname = '/l/secret';
        navigation.search = 'lang=fr';
        const {rerender} = renderTracker();

        expect(matomo.trackPageView).toHaveBeenLastCalledWith(
            `${origin}/l/[token]?lang=fr`,
            'Uploader',
            undefined
        );

        navigation.pathname = '/';
        navigation.search = '';
        rerender();

        expect(matomo.trackPageView).toHaveBeenLastCalledWith(
            `${origin}/`,
            'Uploader',
            `${origin}/l/[token]?lang=fr`
        );
        expect(JSON.stringify(matomo.trackPageView.mock.calls)).not.toContain(
            'secret'
        );
    });

    it('skips the OAuth callback', () => {
        navigation.pathname = '/auth/callback';
        navigation.search = 'code=secret';
        renderTracker();

        expect(matomo.trackPageView).not.toHaveBeenCalled();
    });
});
