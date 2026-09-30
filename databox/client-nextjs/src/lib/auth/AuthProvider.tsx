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
import {
    clearImpersonation,
    getImpersonation,
    onImpersonationChangedElsewhere,
    reloadAsNewUser,
    saveImpersonation,
    startImpersonation,
    stopImpersonation,
    type ImpersonatedUser,
} from './impersonation';
import {toastError} from '@/lib/utils/errors';
import {useConfig} from '@/lib/config/ConfigProvider';
import {getImpersonationIdentity} from '@/lib/api/impersonation';
import {isApiError} from '@/lib/api/http';

export type AuthStatus = 'loading' | 'anonymous' | 'authenticated';

export type AuthContextValue = {
    status: AuthStatus;
    /** The effective user: the impersonated one when an admin switched user */
    user: AuthUser | undefined;
    /** The signed-in user, whoever they act as */
    realUser: AuthUser | undefined;
    impersonating: boolean;
    /** The signed-in user may switch to another user's account */
    canImpersonate: boolean;
    /** Switches to another user's account (reloads the app) */
    impersonate: (user: ImpersonatedUser) => void;
    /** Goes back to the signed-in user's account (reloads the app) */
    stopImpersonating: () => void;
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

function isAdmin(user: AuthUser | undefined): boolean {
    return (
        !!user &&
        (user.roles.includes(AppRole.Admin) ||
            user.roles.includes(AppRole.DataboxAdmin))
    );
}

export function AuthProvider({children}: PropsWithChildren) {
    const config = useConfig();
    const [status, setStatus] = useState<AuthStatus>('loading');
    const [realUser, setRealUser] = useState<AuthUser | undefined>();
    const [impersonated, setImpersonated] = useState<
        ImpersonatedUser | undefined
    >();
    const [sessionExpired, setSessionExpired] = useState(false);
    const [redirecting, setRedirecting] = useState(false);
    const pathname = usePathname();
    const searchParams = useSearchParams();

    useEffect(() => {
        const client = getAuthClient();
        let cancelled = false;

        const sync = () => {
            const u = client.getUser();
            setRealUser(u);
            setImpersonated(u ? getImpersonation() : undefined);
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

    useEffect(() => onImpersonationChangedElsewhere(reloadAsNewUser), []);

    // The stored identity may be outdated (roles, groups), or the switch may
    // no longer be allowed.
    const impersonatedId = impersonated?.id;
    const realUserId = realUser?.id;
    const realUserIsAdmin = isAdmin(realUser);
    useEffect(() => {
        if (!impersonatedId || !realUserId) {
            return;
        }
        if (!config.impersonation || !realUserIsAdmin) {
            stopImpersonation();

            return;
        }
        let cancelled = false;
        getImpersonationIdentity(impersonatedId)
            .then(identity => {
                if (!cancelled) {
                    saveImpersonation(identity);
                    setImpersonated(identity);
                }
            })
            .catch((e: unknown) => {
                if (isApiError(e, 403) || isApiError(e, 404)) {
                    stopImpersonation();
                }
            });

        return () => {
            cancelled = true;
        };
    }, [impersonatedId, realUserId, realUserIsAdmin, config.impersonation]);

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
        clearImpersonation();
        void getAuthClient().logout();
    }, []);

    const canImpersonate = config.impersonation && realUserIsAdmin;
    const user = realUser ? (impersonated ?? realUser) : undefined;

    const impersonate = useCallback(
        (target: ImpersonatedUser) => {
            if (!canImpersonate) {
                return;
            }
            if (target.id === realUserId) {
                stopImpersonation();
            } else {
                startImpersonation(target);
            }
        },
        [canImpersonate, realUserId]
    );

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
            realUser,
            impersonating: !!realUser && !!impersonated,
            canImpersonate,
            impersonate,
            stopImpersonating: stopImpersonation,
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
            realUser,
            impersonated,
            canImpersonate,
            impersonate,
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
