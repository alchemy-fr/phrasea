import type {GroupMember, GroupsApi, KeycloakGroup} from './types';

export type GroupsSearchParams = Record<string, string | number | undefined>;

/**
 * The HTTP calls the groups management needs, authenticated as the user and
 * relative to the API mounting the auth bundle. Errors are expected as
 * `ApiError` (see `utils/errors`), the 204 answers to resolve to nothing.
 */
export type GroupsHttp = {
    get: <T>(
        path: string,
        searchParams?: GroupsSearchParams,
        signal?: AbortSignal
    ) => Promise<T>;
    post: <T>(path: string, json: unknown) => Promise<T>;
    put: <T>(path: string, json?: unknown) => Promise<T>;
    delete: (path: string) => Promise<void>;
};

const groupsPath = '/permissions/groups';

function groupPath(id: string): string {
    return `${groupsPath}/${encodeURIComponent(id)}`;
}

function memberPath(groupId: string, userId: string): string {
    return `${groupPath(groupId)}/members/${encodeURIComponent(userId)}`;
}

function pageParams(
    query: string | undefined,
    offset: number,
    limit: number
): GroupsSearchParams {
    return {query: query?.trim() || undefined, offset, limit};
}

export function createGroupsApi(http: GroupsHttp): GroupsApi {
    return {
        searchGroups: (query, offset, limit, signal) =>
            http.get<KeycloakGroup[]>(
                groupsPath,
                pageParams(query, offset, limit),
                signal
            ),
        createGroup: name => http.post<KeycloakGroup>(groupsPath, {name}),
        renameGroup: (id, name) =>
            http.put<KeycloakGroup>(groupPath(id), {name}),
        deleteGroup: id => http.delete(groupPath(id)),
        getMembers: (groupId, offset, limit, signal) =>
            http.get<GroupMember[]>(
                `${groupPath(groupId)}/members`,
                pageParams(undefined, offset, limit),
                signal
            ),
        addMember: async (groupId, userId) => {
            await http.put(memberPath(groupId, userId));
        },
        removeMember: (groupId, userId) =>
            http.delete(memberPath(groupId, userId)),
        searchUsers: (query, offset, limit, signal) =>
            http.get<GroupMember[]>(
                '/permissions/users',
                pageParams(query, offset, limit),
                signal
            ),
    };
}
