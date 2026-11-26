/**
 * Masks the secrets that some URLs carry (access tokens, authorization
 * codes…) before they leave the browser for a third party: Matomo page views,
 * Sentry events and breadcrumbs.
 *
 * Each client lists its own secret URLs (`createUrlRedactor()`), on top of
 * the ones common to every client (`DEFAULT_URL_REDACTIONS`).
 */

/**
 * A pattern matched against the path of a URL (from its first `/`, query and
 * fragment included, without the origin), and what replaces the match
 * (`String.replace()` syntax: `$1`…)
 */
export type UrlRedaction = {
    pattern: RegExp;
    replacement: string;
};

/** Returns the URL, absolute or relative, with its secrets masked */
export type UrlRedactor = (url: string) => string;

export const DEFAULT_URL_REDACTIONS: readonly UrlRedaction[] = [
    // The OAuth callback carries the authorization code in its query
    {pattern: /^(\/auth\/[^?#]*)[?#].*$/, replacement: '$1'},
];

const ABSOLUTE_URL = /^([a-z][a-z\d+.-]*:\/\/[^/?#]*)(.*)$/i;

export function createUrlRedactor(
    redactions: readonly UrlRedaction[] = []
): UrlRedactor {
    const all = [...DEFAULT_URL_REDACTIONS, ...redactions];

    return url => {
        const absolute = ABSOLUTE_URL.exec(url);
        const origin = absolute ? absolute[1] : '';
        const path = absolute ? absolute[2] : url;

        return (
            origin +
            all.reduce(
                (p, {pattern, replacement}) => p.replace(pattern, replacement),
                path
            )
        );
    };
}

/** Masks the secrets of the URLs common to every client */
export const redactUrl: UrlRedactor = createUrlRedactor();
