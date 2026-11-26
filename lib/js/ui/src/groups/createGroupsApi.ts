import type {GroupMember, GroupsApi, KeycloakGroup} from './types';

/** An array goes out as `key[]=…`, the way Symfony reads it */
export type GroupsSearchParams = Record<
    string,
    string | number | string[] | undefined
>;

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

/** The most groups `/permissions/groups` resolves by ids at once */
const GROUPS_IDS_LIMIT = 50;

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
        getGroupsByIds: async (ids, signal) => {
            const chunks: string[][] = [];
            for (let i = 0; i < ids.length; i += GROUPS_IDS_LIMIT) {
                chunks.push(ids.slice(i, i + GROUPS_IDS_LIMIT));
            }

            return (
                await Promise.all(
                    chunks.map(chunk =>
                        http.get<KeycloakGroup[]>(
                            groupsPath,
                            {ids: chunk},
                            signal
                        )
                    )
                )
            ).flat();
        },
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
