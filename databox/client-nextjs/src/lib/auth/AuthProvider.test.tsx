import {describe, expect, it, vi} from 'vitest';
import {act, renderHook, waitFor} from '@testing-library/react';
import type {PropsWithChildren} from 'react';

vi.mock('next/navigation', () => ({
    usePathname: () => '/assets',
    useSearchParams: () => new URLSearchParams('q=1'),
}));
vi.mock('sonner', () => ({toast: {error: vi.fn()}}));

// Node ships its own (broken, file-backed) localStorage that shadows jsdom's
const storage = new Map<string, string>();
Object.defineProperty(window, 'localStorage', {
    configurable: true,
    value: {
        getItem: (k: string) => storage.get(k) ?? null,
        setItem: (k: string, v: string) => storage.set(k, v),
        removeItem: (k: string) => storage.delete(k),
        clear: () => storage.clear(),
    },
});

const config = vi.hoisted(() => ({impersonation: true}));
vi.mock('@/lib/config/ConfigProvider', () => ({
    useConfig: () => config,
    getConfig: () => config,
}));

const getImpersonationIdentity = vi.hoisted(() => vi.fn());
vi.mock('@/lib/api/impersonation', () => ({getImpersonationIdentity}));

type User = {id: string; username: string; roles: string[]; groups: string[]};

const client = vi.hoisted(() => ({
    init: vi.fn(() => Promise.resolve()),
    getUser: vi.fn<() => User | undefined>(() => undefined),
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

describe('AuthProvider impersonation', () => {
    const admin: User = {
        id: 'admin-id',
        username: 'admin',
        roles: ['admin', 'databox'],
        groups: [],
    };
    const alice: User = {
        id: 'alice-id',
        username: 'alice',
        roles: ['databox'],
        groups: ['g1'],
    };

    it('exposes the impersonated user as the effective one', async () => {
        client.getUser.mockReturnValue(admin);
        window.localStorage.setItem('dbx.impersonation', JSON.stringify(alice));
        getImpersonationIdentity.mockResolvedValue({...alice, groups: ['g2']});

        const {result} = renderHook(() => useAuth(), {wrapper});

        await waitFor(() => expect(result.current.impersonating).toBe(true));
        expect(result.current.realUser).toEqual(admin);
        expect(result.current.user?.id).toBe('alice-id');
        expect(result.current.canImpersonate).toBe(true);
        expect(result.current.hasRole('admin')).toBe(false);

        // The stored identity is refreshed
        await waitFor(() =>
            expect(result.current.user?.groups).toEqual(['g2'])
        );
        expect(getImpersonationIdentity).toHaveBeenCalledWith('alice-id');
    });

    it('does not allow non-admins to switch user', async () => {
        client.getUser.mockReturnValue(alice);
        window.localStorage.clear();

        const {result} = renderHook(() => useAuth(), {wrapper});

        await waitFor(() => expect(result.current.user).toEqual(alice));
        expect(result.current.canImpersonate).toBe(false);
        expect(result.current.impersonating).toBe(false);
    });
});
