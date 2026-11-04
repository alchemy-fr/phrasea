const key = 'expose.logged-out';

/**
 * Remembers, for the browser tab, that the user signed out on purpose: the
 * publications requiring authentication then show their sign-in screen
 * instead of sending the user straight back to Keycloak.
 */
export function setLoggedOut(): void {
    try {
        sessionStorage.setItem(key, '1');
    } catch {
        // storage unavailable
    }
}

export function clearLoggedOut(): void {
    try {
        sessionStorage.removeItem(key);
    } catch {
        // storage unavailable
    }
}

export function hasLoggedOut(): boolean {
    try {
        return sessionStorage.getItem(key) === '1';
    } catch {
        return false;
    }
}
