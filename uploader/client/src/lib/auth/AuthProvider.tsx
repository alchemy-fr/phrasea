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
import type {AuthUser} from '@alchemy/next-core/auth/oidc';
import {toastError} from '@alchemy/next-ui/utils/errors';
import {isUploadLinkPath} from '@/lib/routes';

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
    /**
     * A sign-out is under way: the session is cleared and the browser is
     * leaving for Keycloak. Private screens must not ask for a sign-in
     * meanwhile, it would replace the sign-out navigation.
     */
    loggingOut: boolean;
    sessionExpired: boolean;
    /** Closes the "session expired" dialog and stays signed out */
    dismissSessionExpired: () => void;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({children}: PropsWithChildren) {
    const [status, setStatus] = useState<AuthStatus>('loading');
    const [user, setUser] = useState<AuthUser | undefined>();
    const [sessionExpired, setSessionExpired] = useState(false);
    const [redirecting, setRedirecting] = useState(false);
    const [loggingOut, setLoggingOut] = useState(false);
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
        // exchanged: a silent sign-in would race with it. An upload link is
        // opened by people without an account: nothing to sign in to.
        const path = window.location.pathname;
        client
            .init({
                silent: path !== AUTH_CALLBACK_PATH && !isUploadLinkPath(path),
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
                setLoggingOut(false);
            }
        };
        window.addEventListener('pageshow', onPageShow);

        return () => window.removeEventListener('pageshow', onPageShow);
    }, []);

    const login = useCallback(
        (redirectTo?: string) => {
            const params = new URLSearchParams(searchParams.toString());
            params.delete(LOGGED_OUT_PARAM);
            const qs = params.toString();
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
        setLoggingOut(true);
        void getAuthClient().logout();
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
            redirecting,
            logout,
            loggingOut,
            sessionExpired,
            dismissSessionExpired,
        }),
        [
            status,
            user,
            login,
            redirecting,
            logout,
            loggingOut,
            sessionExpired,
            dismissSessionExpired,
        ]
    );

    return (
        <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
    );
}

/**
 * Carried by the URL Keycloak sends back to after a sign-out: the app then
 * waits for the user to sign in again instead of redirecting to Keycloak
 * straight away.
 */
export const LOGGED_OUT_PARAM = 'logged_out';

export function useAuth(): AuthContextValue {
    const ctx = useContext(AuthContext);
    if (!ctx) {
        throw new Error('useAuth must be used within AuthProvider');
    }

    return ctx;
}
