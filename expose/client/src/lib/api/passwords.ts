import {readCookie, writeCookie} from './cookies';

/**
 * Passwords typed for the publications protected by one, by security
 * container (the publication of the branch defining the method): one
 * password opens a whole tree. Kept in a cookie, base64(JSON), sent to the
 * API in the `X-Passwords` header.
 */
const cookieName = 'passwds';

export function decodePasswords(
    value: string | undefined
): Record<string, string> {
    if (!value) {
        return {};
    }
    try {
        const data = JSON.parse(atob(value));

        return data && typeof data === 'object' && !Array.isArray(data)
            ? data
            : {};
    } catch {
        return {};
    }
}

export function encodePasswords(passwords: Record<string, string>): string {
    return btoa(JSON.stringify(passwords));
}

/** The `X-Passwords` header value, undefined when no password was typed */
export function getPasswordsHeader(): string | undefined {
    return readCookie(cookieName) || undefined;
}

export function storePassword(
    securityContainerId: string,
    password: string
): void {
    const passwords = decodePasswords(readCookie(cookieName));
    passwords[securityContainerId] = password;
    writeCookie(cookieName, encodePasswords(passwords));
}
