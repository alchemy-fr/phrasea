import {ApiError} from '../utils/errors';
import type {GroupMember, GroupsApi, KeycloakGroup} from '../groups/types';

/**
 * An in-memory `GroupsApi` answering as the auth bundle does (409 with a
 * violation on `name` for a taken name), for the tests.
 */
export function createFakeGroupsApi(
    groups: KeycloakGroup[],
    members: Record<string, GroupMember[]> = {},
    users: GroupMember[] = []
) {
    const state = {
        groups: [...groups],
        members: Object.fromEntries(
            Object.entries(members).map(([id, list]) => [id, [...list]])
        ),
    };
    const taken = (name: string, except?: string) =>
        state.groups.some(g => g.name === name && g.id !== except);
    const conflict = () => {
        const message = 'A group with this name already exists.';

        return new ApiError(message, 409, {
            detail: message,
            violations: [{propertyPath: 'name', message}],
        });
    };
    let created = 0;

    const api: GroupsApi = {
        searchGroups: async (query, offset, limit) =>
            state.groups
                .filter(g => g.name.toLowerCase().includes(query.toLowerCase()))
                .slice(offset, offset + limit),
        createGroup: async name => {
            if (taken(name)) {
                throw conflict();
            }
            const group = {id: `new-${++created}`, name, path: `/${name}`};
            state.groups.push(group);

            return group;
        },
        renameGroup: async (id, name) => {
            if (taken(name, id)) {
                throw conflict();
            }
            const group = state.groups.find(g => g.id === id)!;
            group.name = name;

            return {...group};
        },
        deleteGroup: async id => {
            state.groups = state.groups.filter(g => g.id !== id);
        },
        getMembers: async (groupId, offset, limit) =>
            (state.members[groupId] ?? []).slice(offset, offset + limit),
        addMember: async (groupId, userId) => {
            const user = users.find(u => u.id === userId)!;
            state.members[groupId] = [...(state.members[groupId] ?? []), user];
        },
        removeMember: async (groupId, userId) => {
            state.members[groupId] = (state.members[groupId] ?? []).filter(
                m => m.id !== userId
            );
        },
        searchUsers: async (query, offset, limit) =>
            users
                .filter(u =>
                    u.username.toLowerCase().includes(query.toLowerCase())
                )
                .slice(offset, offset + limit),
    };

    return {api, state};
}
