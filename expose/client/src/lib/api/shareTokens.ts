import {readCookie, writeCookie} from './cookies';

/**
 * Tokens of the share links the visitor opened, one cookie per publication
 * (`shareTokens_<id>`, and `shareTokens_<slug>` when it has a slug), the most
 * recent first. Only the requests about that publication send them to the API,
 * in the `X-Share-Tokens` header (comma separated).
 */
const cookiePrefix = 'shareTokens_';

/** Tokens kept per publication */
export const MAX_SHARE_TOKENS = 5;

export type ShareTokenPublication = {id: string; slug?: string | null};

/** The key of the URL (slug or id), or the publication itself */
export type ShareTokenTarget = string | ShareTokenPublication;

function cookieName(key: string): string {
    return `${cookiePrefix}${encodeURIComponent(key)}`;
}

function targetKeys(target: ShareTokenTarget): string[] {
    if (typeof target === 'string') {
        return [target];
    }

    return target.slug ? [target.id, target.slug] : [target.id];
}

export function decodeShareTokens(value: string | undefined): string[] {
    if (!value) {
        return [];
    }

    return value
        .split(',')
        .map(t => t.trim())
        .filter(t => /^[A-Za-z0-9]{1,64}$/.test(t));
}

function readTokens(key: string): string[] {
    return decodeShareTokens(readCookie(cookieName(key)));
}

export function getShareTokens(target: ShareTokenTarget): string[] {
    return [...new Set(targetKeys(target).flatMap(readTokens))];
}

/** The `X-Share-Tokens` header value, undefined without any share link */
export function getShareTokensHeader(
    target: ShareTokenTarget
): string | undefined {
    const tokens = getShareTokens(target);

    return tokens.length > 0 ? tokens.join(',') : undefined;
}

export function storeShareToken(
    publication: ShareTokenPublication,
    token: string
): void {
    for (const key of targetKeys(publication)) {
        const tokens = [token, ...readTokens(key).filter(t => t !== token)];
        writeCookie(
            cookieName(key),
            tokens.slice(0, MAX_SHARE_TOKENS).join(',')
        );
    }
}
