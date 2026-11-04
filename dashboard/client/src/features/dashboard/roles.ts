import type {AppId} from '@/lib/config/types';

/** The Keycloak realm roles the dashboard reads */
export const Role = {
    Admin: 'admin',
    GroupAdmin: 'group-admin',
    UserAdmin: 'user-admin',
    Tech: 'tech',
} as const;

/**
 * Whether the user may open the admin of an application: same rule as the
 * `RoleMapper` of auth-bundle, which grants `ROLE_ADMIN` — required by
 * `/admin` — for `admin` and `<app>-admin`.
 */
export function isAppAdmin(roles: readonly string[], app: AppId): boolean {
    return roles.includes(Role.Admin) || roles.includes(`${app}-admin`);
}

/** Manages the users or the groups of the realm in the Keycloak console */
export function isRealmManager(roles: readonly string[]): boolean {
    return roles.includes(Role.GroupAdmin) || roles.includes(Role.UserAdmin);
}
