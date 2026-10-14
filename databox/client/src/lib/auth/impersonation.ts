import type {AuthUser} from './oidc';

/**
 * Admins can act as another user to test permissions. The admin keeps their
 * own session (tokens) and every API request carries the
 * `X-Impersonate-User` header: the API then evaluates permissions with the
 * target's roles and groups only.
 *
 * The target is persisted in localStorage so the switch survives reloads and
 * is shared with the other tabs.
 */
export const IMPERSONATION_STORAGE_KEY = 'dbx.impersonation';
export const IMPERSONATION_HEADER = 'X-Impersonate-User';

/** Session-scoped caches of the previous user (see features/preferences/store.ts) */
const USER_SESSION_KEYS = ['dbx.prefs'];

export type ImpersonatedUser = AuthUser;

function isImpersonatedUser(v: unknown): v is ImpersonatedUser {
    const u = v as ImpersonatedUser | null;

    return (
        !!u &&
        typeof u.id === 'string' &&
        typeof u.username === 'string' &&
        Array.isArray(u.roles) &&
        Array.isArray(u.groups)
    );
}

export function getImpersonation(): ImpersonatedUser | undefined {
    if (typeof window === 'undefined') {
        return undefined;
    }
    try {
        const raw = window.localStorage.getItem(IMPERSONATION_STORAGE_KEY);
        const parsed: unknown = raw ? JSON.parse(raw) : undefined;

        return isImpersonatedUser(parsed) ? parsed : undefined;
    } catch {
        return undefined;
    }
}

export function saveImpersonation(user: ImpersonatedUser): void {
    try {
        const value = JSON.stringify(user);
        if (window.localStorage.getItem(IMPERSONATION_STORAGE_KEY) !== value) {
            window.localStorage.setItem(IMPERSONATION_STORAGE_KEY, value);
        }
    } catch {
        // ignore
    }
}

function parseUserId(raw: string | null): string | undefined {
    try {
        const parsed: unknown = raw ? JSON.parse(raw) : undefined;

        return isImpersonatedUser(parsed) ? parsed.id : undefined;
    } catch {
        return undefined;
    }
}

export function clearImpersonation(): void {
    try {
        window.localStorage.removeItem(IMPERSONATION_STORAGE_KEY);
    } catch {
        // ignore
    }
}

/**
 * Every store (React Query, Zustand, realtime channels…) holds data of the
 * previous user: a full reload is the only reliable reset. The current page
 * is kept, so its permissions can be checked right away.
 */
export function reloadAsNewUser(): void {
    USER_SESSION_KEYS.forEach(key => {
        try {
            window.sessionStorage.removeItem(key);
        } catch {
            // ignore
        }
    });
    window.location.reload();
}

export function startImpersonation(user: ImpersonatedUser): void {
    saveImpersonation(user);
    reloadAsNewUser();
}

export function stopImpersonation(): void {
    clearImpersonation();
    reloadAsNewUser();
}

/**
 * Calls back when another tab switches user (an identity refresh of the
 * same user is ignored).
 */
export function onImpersonationChangedElsewhere(
    callback: () => void
): () => void {
    const listener = (e: StorageEvent) => {
        if (
            e.key === IMPERSONATION_STORAGE_KEY &&
            parseUserId(e.oldValue) !== parseUserId(e.newValue)
        ) {
            callback();
        }
    };
    window.addEventListener('storage', listener);

    return () => window.removeEventListener('storage', listener);
}
