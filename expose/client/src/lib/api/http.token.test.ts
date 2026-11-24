import {beforeEach, describe, expect, it, vi} from 'vitest';
import {api} from './http';

vi.mock('@/lib/config/ConfigProvider', () => ({
    getConfig: () => ({apiUrl: 'https://api.test'}),
}));

const {refresh} = vi.hoisted(() => ({refresh: vi.fn()}));
vi.mock('@/lib/auth/client', () => ({
    getAuthClient: () => ({
        getAccessToken: () => Promise.resolve('user-token'),
        hasSession: () => true,
        refresh,
    }),
}));

const fetchMock = vi.fn();
vi.stubGlobal('fetch', fetchMock);

function jsonResponse(status = 200): Response {
    return new Response('{}', {
        status,
        headers: {'Content-Type': 'application/json'},
    });
}

async function sentHeaders(call: () => Promise<unknown>): Promise<Headers> {
    fetchMock.mockResolvedValueOnce(jsonResponse());
    await call();

    return (fetchMock.mock.calls.at(-1)![0] as Request).headers;
}

describe('access token', () => {
    beforeEach(() => {
        fetchMock.mockReset();
        refresh.mockReset();
    });

    it('is sent to the API by a relative path', async () => {
        const headers = await sentHeaders(() => api.get('/assets'));
        expect(headers.get('Authorization')).toBe('Bearer user-token');
    });

    it('is sent to an absolute URL of the API', async () => {
        const headers = await sentHeaders(() =>
            api.get('https://api.test/assets')
        );
        expect(headers.get('Authorization')).toBe('Bearer user-token');
    });

    it('is not sent to another host', async () => {
        const headers = await sentHeaders(() =>
            api.get('https://s3.test/file')
        );
        expect(headers.has('Authorization')).toBe(false);
    });

    it('is not refreshed on a 401 from another host', async () => {
        fetchMock.mockResolvedValueOnce(jsonResponse(401));
        await expect(api.get('https://s3.test/file')).rejects.toThrow();
        expect(refresh).not.toHaveBeenCalled();
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('is refreshed on a 401 from the API', async () => {
        refresh.mockResolvedValueOnce({accessToken: 'new-token'});
        fetchMock
            .mockResolvedValueOnce(jsonResponse(401))
            .mockResolvedValueOnce(jsonResponse());
        await api.get('/assets');
        expect(refresh).toHaveBeenCalledTimes(1);
        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(
            (fetchMock.mock.calls[1][0] as Request).headers.get('Authorization')
        ).toBe('Bearer new-token');
    });
});
