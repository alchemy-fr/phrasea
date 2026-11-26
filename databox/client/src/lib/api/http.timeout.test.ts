import {beforeEach, describe, expect, it, vi} from 'vitest';
import {ApiError, api, isTimeoutError} from './http';

vi.mock('@/lib/config/ConfigProvider', () => ({
    getConfig: () => ({apiUrl: 'https://api.test'}),
}));

vi.mock('@/lib/auth/client', () => ({
    getAuthClient: () => ({
        getAccessToken: () => Promise.resolve('user-token'),
        hasSession: () => true,
        refresh: vi.fn(),
    }),
}));

const fetchMock = vi.fn();
vi.stubGlobal('fetch', fetchMock);

function respondWith(status: number): void {
    fetchMock.mockImplementation(
        async () =>
            new Response('{}', {
                status,
                headers: {'Content-Type': 'application/json'},
            })
    );
}

async function caught(call: () => Promise<unknown>): Promise<unknown> {
    try {
        await call();
    } catch (e) {
        return e;
    }

    throw new Error('The request did not fail');
}

describe('timeouts', () => {
    beforeEach(() => {
        fetchMock.mockReset();
    });

    it('detects the client giving up', async () => {
        fetchMock.mockImplementation(() => new Promise(() => {}));

        const error = await caught(() => api.get('/assets', {timeout: 10}));

        expect(isTimeoutError(error)).toBe(true);
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('does not replay a gateway timeout', async () => {
        respondWith(504);

        const error = await caught(() => api.get('/assets'));

        expect(error).toBeInstanceOf(ApiError);
        expect(isTimeoutError(error)).toBe(true);
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('still replays an unavailable API', async () => {
        respondWith(503);

        const error = await caught(() => api.get('/assets'));

        expect(isTimeoutError(error)).toBe(false);
        expect(fetchMock).toHaveBeenCalledTimes(3);
    });

    it('does not take other errors for timeouts', async () => {
        respondWith(500);

        expect(isTimeoutError(await caught(() => api.get('/assets')))).toBe(
            false
        );
        expect(isTimeoutError(new Error('Request timed out'))).toBe(false);
    });
});
