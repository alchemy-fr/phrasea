/**
 * Passwords typed for the publications protected by one, by security
 * container (the publication of the branch defining the method): one
 * password opens a whole tree. Kept in a cookie, base64(JSON), sent to the
 * API in the `X-Passwords` header. `SameSite=None` keeps it usable when the
 * page is embedded in an iframe of another site.
 */
const cookieName = 'passwds';

function readCookie(name: string): string | undefined {
    if (typeof document === 'undefined') {
        return undefined;
    }
    const prefix = `${name}=`;
    const entry = document.cookie
        .split(';')
        .map(c => c.trim())
        .find(c => c.startsWith(prefix));

    return entry ? decodeURIComponent(entry.slice(prefix.length)) : undefined;
}

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
    const secure = window.location.protocol === 'https:';
    document.cookie = [
        `${cookieName}=${encodeURIComponent(encodePasswords(passwords))}`,
        'path=/',
        'max-age=31536000',
        // A cookie with SameSite=None must be Secure
        secure ? 'SameSite=None; Secure' : 'SameSite=Lax',
    ].join('; ');
}
