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
import {AUTH_CALLBACK_PATH, getAuthClient} from './client';
import type {AuthUser} from './oidc';
import {LOGIN_WINDOW_PATH, openLoginWindow} from './loginWindow';
import {toastError} from '@/lib/utils/errors';

export type AuthStatus = 'loading' | 'anonymous' | 'authenticated';

export type AuthContextValue = {
    status: AuthStatus;
    user: AuthUser | undefined;
    isAuthenticated: boolean;
    /** Redirects to Keycloak, coming back to the current page by default */
    login: (redirectTo?: string) => void;
    /**
     * Signs in from a separate window, for a page embedded in an iframe of
     * another site (Keycloak refuses to be framed). Resolves once signed in,
     * to false when the window was closed (or blocked) before.
     */
    loginInWindow: () => Promise<boolean>;
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

/** Pages where the session must not be checked silently on load */
const noSilentLoginPaths = [AUTH_CALLBACK_PATH, LOGIN_WINDOW_PATH];

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

        // On the callback page, the code of the interactive sign-in is being
        // exchanged: a silent sign-in would race with it.
        client
            .init({
                silent: !noSilentLoginPaths.includes(window.location.pathname),
            })
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

    // Back to the page the user was on: every page is public
    const logout = useCallback(() => {
        void getAuthClient().logout({
            redirectUri: window.location.href.split('#')[0],
        });
    }, []);

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
            loginInWindow: openLoginWindow,
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
