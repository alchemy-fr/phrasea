import type {ShareAccess} from '@/lib/api/misc';

function key(shareId: string): string {
    return `share.access.${shareId}`;
}

/**
 * The access token of a share protected by a password, kept for the browser
 * tab (`sessionStorage`) once the password is given: the share page opens
 * without asking it again until the token expires.
 */
export function getStoredShareAccess(shareId: string): string | undefined {
    try {
        const raw = sessionStorage.getItem(key(shareId));
        if (!raw) {
            return undefined;
        }
        const value = JSON.parse(raw) as Partial<ShareAccess>;
        if (
            typeof value.access !== 'string' ||
            typeof value.expiresAt !== 'string' ||
            !(new Date(value.expiresAt).getTime() > Date.now())
        ) {
            sessionStorage.removeItem(key(shareId));

            return undefined;
        }

        return value.access;
    } catch {
        return undefined;
    }
}

export function storeShareAccess(shareId: string, access: ShareAccess): void {
    try {
        sessionStorage.setItem(
            key(shareId),
            JSON.stringify({
                access: access.access,
                expiresAt: access.expiresAt,
            })
        );
    } catch {
        // Storage unavailable: the password is asked again on the next visit
    }
}

export function clearShareAccess(shareId: string): void {
    try {
        sessionStorage.removeItem(key(shareId));
    } catch {
        // Storage unavailable
    }
}
