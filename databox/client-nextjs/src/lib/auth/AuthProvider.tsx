'use client';

import {
    createContext,
    PropsWithChildren,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';
import {usePathname, useSearchParams} from 'next/navigation';
import {getAuthClient} from './client';
import type {AuthUser} from './oidc';

export type AuthStatus = 'loading' | 'anonymous' | 'authenticated';

export type AuthContextValue = {
    status: AuthStatus;
    user: AuthUser | undefined;
    isAuthenticated: boolean;
    /** Redirects to Keycloak, coming back to the current page by default */
    login: (redirectTo?: string) => void;
    logout: () => void;
    hasRole: (role: string) => boolean;
    sessionExpired: boolean;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export enum AppRole {
    Admin = 'admin',
    DataboxAdmin = 'databox-admin',
    Tech = 'tech',
}

export function AuthProvider({children}: PropsWithChildren) {
    const [status, setStatus] = useState<AuthStatus>('loading');
    const [user, setUser] = useState<AuthUser | undefined>();
    const [sessionExpired, setSessionExpired] = useState(false);
    const pathname = usePathname();
    const searchParams = useSearchParams();

    useEffect(() => {
        const client = getAuthClient();
        let cancelled = false;

        const sync = () => {
            const u = client.getUser();
            setUser(u);
            setStatus(u ? 'authenticated' : 'anonymous');
        };

        const unsubscribe = client.subscribe(event => {
            if (event === 'expired') {
                setSessionExpired(true);
            }
            if (event === 'login') {
                setSessionExpired(false);
            }
            sync();
        });

        client
            .init()
            .catch(() => undefined)
            .finally(() => {
                if (!cancelled) {
                    sync();
                }
            });

        return () => {
            cancelled = true;
            unsubscribe();
        };
    }, []);

    const login = useCallback(
        (redirectTo?: string) => {
            const qs = searchParams.toString();
            const current = `${pathname}${qs ? `?${qs}` : ''}${
                typeof window !== 'undefined' ? window.location.hash : ''
            }`;
            void getAuthClient().login(redirectTo ?? current);
        },
        [pathname, searchParams]
    );

    const logout = useCallback(() => {
        void getAuthClient().logout();
    }, []);

    const value = useMemo<AuthContextValue>(
        () => ({
            status,
            user,
            isAuthenticated: status === 'authenticated',
            login,
            logout,
            hasRole: role => !!user?.roles.includes(role),
            sessionExpired,
        }),
        [status, user, login, logout, sessionExpired]
    );

    return (
        <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
    );
}

export function useAuth(): AuthContextValue {
    const ctx = useContext(AuthContext);
    if (!ctx) {
        throw new Error('useAuth must be used within AuthProvider');
    }

    return ctx;
}
