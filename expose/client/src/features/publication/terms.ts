/**
 * Acceptance of the terms of a publication, remembered in a cookie for a
 * year: `terms_p_{id}` for the terms of use, `terms_pd_{id}` for the
 * download terms.
 */
const prefix = 'terms_';

export function termsKey(kind: 'view' | 'download', publicationId: string) {
    return `${prefix}${kind === 'view' ? 'p' : 'pd'}_${publicationId}`;
}

export function isTermsAccepted(key: string): boolean {
    if (typeof document === 'undefined') {
        return false;
    }

    return document.cookie.split(';').some(c => c.trim() === `${key}=1`);
}

export function acceptTerms(key: string): void {
    const secure = window.location.protocol === 'https:';
    document.cookie = [
        `${key}=1`,
        'path=/',
        'max-age=31536000',
        // Accepted in an embedded page too
        secure ? 'SameSite=None; Secure' : 'SameSite=Lax',
    ].join('; ');
}
