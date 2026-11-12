/** A Keycloak group of the realm */
export type KeycloakGroup = {
    id: string;
    name: string;
    path?: string | null;
};

/** A Keycloak user, in its brief representation */
export type GroupMember = {
    id: string;
    username: string;
    firstName?: string | null;
    lastName?: string | null;
    email?: string | null;
};

/**
 * The calls managing the Keycloak groups (`/permissions/groups…` and
 * `/permissions/users` of the auth bundle). Keycloak checks them with the
 * token of the user: the writes need the `manage-users` role of
 * `realm-management`.
 */
export type GroupsApi = {
    /** A page of the groups matching `query` (all of them when empty) */
    searchGroups: (
        query: string,
        offset: number,
        limit: number,
        signal?: AbortSignal
    ) => Promise<KeycloakGroup[]>;
    /** Creates a top level group */
    createGroup: (name: string) => Promise<KeycloakGroup>;
    renameGroup: (id: string, name: string) => Promise<KeycloakGroup>;
    deleteGroup: (id: string) => Promise<void>;
    /** A page of the direct members of the group */
    getMembers: (
        groupId: string,
        offset: number,
        limit: number,
        signal?: AbortSignal
    ) => Promise<GroupMember[]>;
    addMember: (groupId: string, userId: string) => Promise<void>;
    removeMember: (groupId: string, userId: string) => Promise<void>;
    /** A page of the users matching `query` */
    searchUsers: (
        query: string,
        offset: number,
        limit: number,
        signal?: AbortSignal
    ) => Promise<GroupMember[]>;
};

/** Page size of the groups, members and users, paged by offset */
export const GROUPS_PAGE_SIZE = 30;

/** Prefix of the query keys of the groups management */
export const groupsQueryKey = ['keycloak-groups'] as const;

/**
 * Query key of the group pickers of the clients (`GroupSelect` of databox):
 * stale as soon as a group is created, renamed or deleted.
 */
export const groupPickerQueryKey = ['groups'] as const;

/** "First Last", else the username */
export function memberDisplayName(member: GroupMember): string {
    return (
        [member.firstName, member.lastName].filter(Boolean).join(' ') ||
        member.username
    );
}
