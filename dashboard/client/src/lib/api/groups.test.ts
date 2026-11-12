import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {isApiError} from '@alchemy/next-ui/utils/errors';
import {createDataboxGroupsApi} from './groups';

vi.mock('@/lib/auth/client', () => ({
    getAuthClient: () => ({getAccessToken: async () => 'the-token'}),
}));

const fetchMock = vi.fn();

function respond(status: number, body?: unknown) {
    fetchMock.mockResolvedValueOnce(
        new Response(body === undefined ? null : JSON.stringify(body), {
            status,
            headers: {'Content-Type': 'application/json'},
        })
    );
}

describe('createDataboxGroupsApi', () => {
    beforeEach(() => {
        vi.stubGlobal('fetch', fetchMock);
    });
    afterEach(() => {
        vi.unstubAllGlobals();
        fetchMock.mockReset();
    });

    it('calls the Databox API with the token of the user', async () => {
        const api = createDataboxGroupsApi('https://api-databox.test/');
        respond(200, [{id: 'g1', name: 'Sales'}]);

        expect(await api.searchGroups('sal', 0, 30)).toEqual([
            {id: 'g1', name: 'Sales'},
        ]);
        const [url, init] = fetchMock.mock.calls[0];
        expect(String(url)).toBe(
            'https://api-databox.test/permissions/groups?query=sal&offset=0&limit=30'
        );
        expect(init.method).toBe('GET');
        expect(init.headers.Authorization).toBe('Bearer the-token');
        expect(init.body).toBeUndefined();
    });

    it('sends the JSON body of the writes', async () => {
        const api = createDataboxGroupsApi('https://api-databox.test');
        respond(201, {id: 'g2', name: 'Design'});
        respond(204);

        expect(await api.createGroup('Design')).toEqual({
            id: 'g2',
            name: 'Design',
        });
        await api.addMember('g2', 'u1');

        const [, create] = fetchMock.mock.calls[0];
        expect(create.method).toBe('POST');
        expect(create.headers['Content-Type']).toBe('application/json');
        expect(create.body).toBe('{"name":"Design"}');
        const [memberUrl, member] = fetchMock.mock.calls[1];
        expect(String(memberUrl)).toBe(
            'https://api-databox.test/permissions/groups/g2/members/u1'
        );
        expect(member.method).toBe('PUT');
    });

    it('throws the refusals as API errors', async () => {
        const api = createDataboxGroupsApi('https://api-databox.test');
        const message = 'A group with this name already exists.';
        respond(409, {
            detail: message,
            violations: [{propertyPath: 'name', message}],
        });

        const error = await api.createGroup('Sales').catch(e => e);

        expect(isApiError(error, 409)).toBe(true);
        expect(error.message).toBe(message);
        expect(error.violations).toEqual([{propertyPath: 'name', message}]);
    });
});
