import {ShareTerms} from '../../types.ts';

const storageKeyPrefix = 'share.terms.accepted.';

function getStorageKey(shareId: string): string {
    return `${storageKeyPrefix}${shareId}`;
}

/**
 * Whether the given terms version was already accepted on this browser.
 * Must only be called client-side (after mount).
 */
export function hasAcceptedShareTerms(
    shareId: string,
    terms: ShareTerms
): boolean {
    try {
        return (
            window.localStorage.getItem(getStorageKey(shareId)) ===
            String(terms.version)
        );
    } catch (_e) {
        return false;
    }
}

export function storeAcceptedShareTerms(
    shareId: string,
    terms: ShareTerms
): void {
    try {
        window.localStorage.setItem(
            getStorageKey(shareId),
            String(terms.version)
        );
    } catch (_e) {
        // Storage unavailable (private mode, disabled…): acceptance
        // only lasts for the current page.
    }
}
