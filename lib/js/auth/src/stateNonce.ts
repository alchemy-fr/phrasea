const storageKey = 'auth.state_nonces';
// One nonce per page (tab) still waiting for its callback: keep the latest ones only.
const maxNonces = 20;

let pageNonce: string | undefined;

function readNonces(storage: Storage): string[] {
    const value = storage.getItem(storageKey);
    if (!value) {
        return [];
    }

    try {
        const nonces = JSON.parse(value);

        return Array.isArray(nonces) ? nonces : [];
    } catch {
        return [];
    }
}

/**
 * The storage of the window that opened the login popup, when it is another one:
 * an embedded (iframe) page gets a storage partitioned from the popup's.
 */
function getOpenerStorage(): Storage | undefined {
    try {
        const storage = window.opener?.localStorage as Storage | undefined;

        return storage && storage !== window.localStorage ? storage : undefined;
    } catch {
        return undefined;
    }
}

/**
 * The nonce of the login links of this page: login URLs are built on every render,
 * a new nonce each time would push out the ones of the other tabs.
 */
export function createStateNonce(): string {
    let nonces: string[];
    try {
        nonces = readNonces(window.localStorage);
    } catch {
        // Storage unavailable: the callback cannot check the nonce either
        return (pageNonce ??= crypto.randomUUID());
    }

    if (pageNonce && nonces.includes(pageNonce)) {
        return pageNonce;
    }

    pageNonce = crypto.randomUUID();
    try {
        window.localStorage.setItem(
            storageKey,
            JSON.stringify([...nonces, pageNonce].slice(-maxNonces))
        );
    } catch {
        // Storage read-only: the callback will refuse the nonce
    }

    return pageNonce;
}

function consumeFrom(storage: Storage, nonce: string): boolean {
    const nonces = readNonces(storage);
    if (!nonces.includes(nonce)) {
        return false;
    }

    try {
        storage.setItem(
            storageKey,
            JSON.stringify(nonces.filter(n => n !== nonce))
        );
    } catch {
        // Storage read-only: the nonce stays, it was still issued by this browser
    }

    return true;
}

/**
 * Whether the authorization response answers a request started from this browser,
 * so that an attacker cannot log the user into the attacker's account (login CSRF).
 * A nonce is valid once.
 */
export function consumeStateNonce(nonce: unknown): boolean {
    let found: boolean;
    try {
        found =
            typeof nonce === 'string' &&
            consumeFrom(window.localStorage, nonce);
    } catch {
        return true;
    }

    if (found) {
        return true;
    }

    const openerStorage = getOpenerStorage();
    try {
        return (
            typeof nonce === 'string' &&
            undefined !== openerStorage &&
            consumeFrom(openerStorage, nonce)
        );
    } catch {
        return false;
    }
}
