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
import {toastError} from '@/lib/utils/errors';

export type AuthStatus = 'loading' | 'anonymous' | 'authenticated';

export type AuthContextValue = {
    status: AuthStatus;
    user: AuthUser | undefined;
    isAuthenticated: boolean;
    /** Redirects to Keycloak, coming back to the current page by default */
    login: (redirectTo?: string) => void;
    /**
     * A sign-in redirection is under way: the PKCE challenge is being
     * computed, or the browser is leaving for Keycloak. Sign-in buttons show
     * a spinner meanwhile.
     */
    redirecting: boolean;
    logout: () => void;
    hasRole: (role: string) => boolean;
    sessionExpired: boolean;
    /** Closes the "session expired" dialog and stays signed out */
    dismissSessionExpired: () => void;
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
    const [redirecting, setRedirecting] = useState(false);
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

    // The page is restored from the back/forward cache when the user comes
    // back from Keycloak with the browser's back button: the redirection
    // state set before leaving is still there, and would stick.
    useEffect(() => {
        const onPageShow = (e: PageTransitionEvent) => {
            if (e.persisted) {
                setRedirecting(false);
            }
        };
        window.addEventListener('pageshow', onPageShow);

        return () => window.removeEventListener('pageshow', onPageShow);
    }, []);

    const login = useCallback(
        (redirectTo?: string) => {
            const qs = searchParams.toString();
            const current = `${pathname}${qs ? `?${qs}` : ''}${
                typeof window !== 'undefined' ? window.location.hash : ''
            }`;
            // Stays on until the page unloads: `login()` resolves as soon as
            // the navigation is requested, well before the browser leaves.
            setRedirecting(true);
            getAuthClient()
                .login(redirectTo ?? current)
                .catch((e: unknown) => {
                    setRedirecting(false);
                    toastError(e);
                });
        },
        [pathname, searchParams]
    );

    const logout = useCallback(() => {
        void getAuthClient().logout();
    }, []);

    // Signing in again is not the only way out: the public parts of the app
    // (shared links, public pages) are still usable while signed out.
    const dismissSessionExpired = useCallback(
        () => setSessionExpired(false),
        []
    );

    const value = useMemo<AuthContextValue>(
        () => ({
            status,
            user,
            isAuthenticated: status === 'authenticated',
            login,
            redirecting,
            logout,
            hasRole: role => !!user?.roles.includes(role),
            sessionExpired,
            dismissSessionExpired,
        }),
        [
            status,
            user,
            login,
            redirecting,
            logout,
            sessionExpired,
            dismissSessionExpired,
        ]
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
