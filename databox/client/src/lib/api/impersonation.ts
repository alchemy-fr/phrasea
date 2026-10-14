import {getUserDisplayName} from '@/lib/users';
import {api} from './http';
import type {ImpersonatedUser} from '@/lib/auth/impersonation';

// These routes are admin-only: they are always called as the real admin.

export type ImpersonableUser = {
    id: string;
    username: string;
    email?: string | null;
    firstName?: string | null;
    lastName?: string | null;
    enabled: boolean;
};

type ImpersonationIdentity = {
    id: string;
    username: string;
    email?: string | null;
    firstName?: string | null;
    lastName?: string | null;
    roles: string[];
    groups: string[];
};

export function getImpersonableUsers(
    query?: string,
    signal?: AbortSignal
): Promise<ImpersonableUser[]> {
    return api.get<ImpersonableUser[]>('/impersonation/users', {
        params: {query: query || undefined, limit: 50},
        signal,
        asRealUser: true,
    });
}

export const getDisplayName = getUserDisplayName;

export async function getImpersonationIdentity(
    id: string
): Promise<ImpersonatedUser> {
    const identity = await api.get<ImpersonationIdentity>(
        `/impersonation/users/${encodeURIComponent(id)}`,
        {asRealUser: true}
    );

    return {
        id: identity.id,
        username: identity.username,
        email: identity.email ?? undefined,
        name:
            [identity.firstName, identity.lastName].filter(Boolean).join(' ') ||
            undefined,
        roles: identity.roles,
        groups: identity.groups,
    };
}
