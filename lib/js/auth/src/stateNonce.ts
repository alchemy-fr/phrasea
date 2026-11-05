const storageKey = 'auth.state_nonces';
// Each login link rendered stores its nonce: keep the latest ones only.
const maxNonces = 20;

function readNonces(): string[] {
    const value = window.localStorage.getItem(storageKey);
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

export function createStateNonce(): string {
    const nonce = crypto.randomUUID();
    try {
        window.localStorage.setItem(
            storageKey,
            JSON.stringify([...readNonces(), nonce].slice(-maxNonces))
        );
    } catch {
        // Storage unavailable: the callback cannot check the nonce either
    }

    return nonce;
}

/**
 * Whether the authorization response answers a request started from this browser,
 * so that an attacker cannot log the user into the attacker's account (login CSRF).
 * A nonce is valid once.
 */
export function consumeStateNonce(nonce: unknown): boolean {
    let nonces: string[];
    try {
        nonces = readNonces();
    } catch {
        return true;
    }

    if (typeof nonce !== 'string' || !nonces.includes(nonce)) {
        return false;
    }

    try {
        window.localStorage.setItem(
            storageKey,
            JSON.stringify(nonces.filter(n => n !== nonce))
        );
    } catch {
        // Storage read-only: the nonce stays, it was still issued by this browser
    }

    return true;
}
