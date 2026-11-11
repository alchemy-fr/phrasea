import {readCookie, writeCookie} from './cookies';

/**
 * Tokens of the share links the visitor opened, the most recent first. Kept
 * in a cookie, sent to the API in the `X-Share-Tokens` header (comma
 * separated) of every request.
 */
const cookieName = 'shareTokens';

/** The API reads this many tokens at most */
export const MAX_SHARE_TOKENS = 20;

export function decodeShareTokens(value: string | undefined): string[] {
    if (!value) {
        return [];
    }

    return value
        .split(',')
        .map(t => t.trim())
        .filter(t => /^[A-Za-z0-9]{1,64}$/.test(t));
}

export function getShareTokens(): string[] {
    return decodeShareTokens(readCookie(cookieName));
}

/** The `X-Share-Tokens` header value, undefined without any share link */
export function getShareTokensHeader(): string | undefined {
    const tokens = getShareTokens();

    return tokens.length > 0 ? tokens.join(',') : undefined;
}

export function storeShareToken(token: string): void {
    const tokens = [token, ...getShareTokens().filter(t => t !== token)].slice(
        0,
        MAX_SHARE_TOKENS
    );
    writeCookie(cookieName, tokens.join(','));
}
