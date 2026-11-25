import {describe, expect, it, vi} from 'vitest';
import {createGroupsApi, type GroupsHttp} from './createGroupsApi';

function fakeHttp() {
    return {
        get: vi.fn(async () => []),
        post: vi.fn(async () => ({id: 'g1', name: 'G'})),
        put: vi.fn(async () => undefined),
        delete: vi.fn(async () => undefined),
    };
}

const create = (http: ReturnType<typeof fakeHttp>) =>
    createGroupsApi(http as unknown as GroupsHttp);

describe('createGroupsApi', () => {
    it('calls the group endpoints of the auth bundle', async () => {
        const http = fakeHttp();
        const api = create(http);
        const signal = new AbortController().signal;

        await api.searchGroups(' mark ', 30, 30, signal);
        expect(http.get).toHaveBeenLastCalledWith(
            '/permissions/groups',
            {query: 'mark', offset: 30, limit: 30},
            signal
        );

        await api.searchGroups('', 0, 30);
        expect(http.get).toHaveBeenLastCalledWith(
            '/permissions/groups',
            {query: undefined, offset: 0, limit: 30},
            undefined
        );

        await api.createGroup('Sales');
        expect(http.post).toHaveBeenCalledWith('/permissions/groups', {
            name: 'Sales',
        });

        await api.renameGroup('g1', 'Sales team');
        expect(http.put).toHaveBeenLastCalledWith('/permissions/groups/g1', {
            name: 'Sales team',
        });

        await api.deleteGroup('g1');
        expect(http.delete).toHaveBeenLastCalledWith('/permissions/groups/g1');
    });

    it('calls the member endpoints and searches the users', async () => {
        const http = fakeHttp();
        const api = create(http);

        await api.getMembers('g1', 0, 30);
        expect(http.get).toHaveBeenLastCalledWith(
            '/permissions/groups/g1/members',
            {query: undefined, offset: 0, limit: 30},
            undefined
        );

        await api.addMember('g1', 'u1');
        expect(http.put).toHaveBeenLastCalledWith(
            '/permissions/groups/g1/members/u1'
        );

        await api.removeMember('g1', 'u1');
        expect(http.delete).toHaveBeenLastCalledWith(
            '/permissions/groups/g1/members/u1'
        );

        await api.searchUsers('ali', 0, 30);
        expect(http.get).toHaveBeenLastCalledWith(
            '/permissions/users',
            {query: 'ali', offset: 0, limit: 30},
            undefined
        );
    });

    it('resolves the groups by ids, 50 at most per call', async () => {
        const http = {
            ...fakeHttp(),
            get: vi.fn(async (_path: string, params: {ids: string[]}) =>
                params.ids.map(id => ({id, name: id}))
            ),
        };
        const api = createGroupsApi(http as unknown as GroupsHttp);
        const ids = Array.from({length: 51}, (_, i) => `g${i}`);

        const groups = await api.getGroupsByIds(ids);

        expect(groups.map(g => g.id)).toEqual(ids);
        expect(http.get).toHaveBeenCalledTimes(2);
        expect(http.get).toHaveBeenNthCalledWith(
            1,
            '/permissions/groups',
            {ids: ids.slice(0, 50)},
            undefined
        );
        expect(http.get).toHaveBeenNthCalledWith(
            2,
            '/permissions/groups',
            {ids: ['g50']},
            undefined
        );
    });

    it('resolves no id without a call', async () => {
        const http = fakeHttp();

        expect(await create(http).getGroupsByIds([])).toEqual([]);
        expect(http.get).not.toHaveBeenCalled();
    });

    it('encodes the ids in the paths', async () => {
        const http = fakeHttp();

        await create(http).deleteGroup('a/b');

        expect(http.delete).toHaveBeenCalledWith('/permissions/groups/a%2Fb');
    });
});
