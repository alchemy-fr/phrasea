export type UserLike = {
    username: string;
    displayName?: string | null;
    firstName?: string | null;
    lastName?: string | null;
};

/**
 * Human-readable name of a user: the API-computed displayName when present,
 * else "First Last" from Keycloak, else the username.
 */
export function getUserDisplayName(u: UserLike): string {
    return (
        u.displayName ||
        [u.firstName, u.lastName].filter(Boolean).join(' ') ||
        u.username
    );
}
