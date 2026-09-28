import {describe, expect, it, vi} from 'vitest';
import {act, renderHook} from '@testing-library/react';
import type {PropsWithChildren} from 'react';

vi.mock('next/navigation', () => ({
    usePathname: () => '/assets',
    useSearchParams: () => new URLSearchParams('q=1'),
}));
vi.mock('sonner', () => ({toast: {error: vi.fn()}}));

const client = vi.hoisted(() => ({
    init: vi.fn(() => Promise.resolve()),
    getUser: vi.fn(() => undefined),
    subscribe: vi.fn(() => () => undefined),
    login: vi.fn<(redirectTo: string) => Promise<void>>(() =>
        Promise.resolve()
    ),
    logout: vi.fn(),
}));
vi.mock('./client', () => ({getAuthClient: () => client}));

const {AuthProvider, useAuth} = await import('./AuthProvider');

const wrapper = ({children}: PropsWithChildren) => (
    <AuthProvider>{children}</AuthProvider>
);

describe('AuthProvider login', () => {
    it('reports the redirection until the page unloads', async () => {
        const {result} = renderHook(() => useAuth(), {wrapper});
        expect(result.current.redirecting).toBe(false);

        act(() => result.current.login());
        expect(result.current.redirecting).toBe(true);
        expect(client.login).toHaveBeenCalledWith('/assets?q=1');

        // The navigation is requested: the state stays on
        await act(async () => undefined);
        expect(result.current.redirecting).toBe(true);
    });

    it('resets when the page is restored from the bfcache', () => {
        const {result} = renderHook(() => useAuth(), {wrapper});
        act(() => result.current.login());
        expect(result.current.redirecting).toBe(true);

        act(() => {
            const e = new Event('pageshow');
            Object.defineProperty(e, 'persisted', {value: true});
            window.dispatchEvent(e);
        });
        expect(result.current.redirecting).toBe(false);
    });

    it('resets when the redirection fails', async () => {
        client.login.mockRejectedValueOnce(new Error('nope'));
        const {result} = renderHook(() => useAuth(), {wrapper});
        act(() => result.current.login());
        expect(result.current.redirecting).toBe(true);

        await act(async () => undefined);
        expect(result.current.redirecting).toBe(false);
    });
});
