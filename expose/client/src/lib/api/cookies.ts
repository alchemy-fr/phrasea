/**
 * The cookies holding what the visitor gave to open publications (passwords,
 * share links), sent back to the API in headers. `SameSite=None` keeps them
 * usable when the page is embedded in an iframe of another site.
 */
export function readCookie(name: string): string | undefined {
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

/** Kept one year */
export function writeCookie(name: string, value: string): void {
    const secure = window.location.protocol === 'https:';
    document.cookie = [
        `${name}=${encodeURIComponent(value)}`,
        'path=/',
        'max-age=31536000',
        // A cookie with SameSite=None must be Secure
        secure ? 'SameSite=None; Secure' : 'SameSite=Lax',
    ].join('; ');
}
