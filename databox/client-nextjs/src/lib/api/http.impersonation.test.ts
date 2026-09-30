import {beforeEach, describe, expect, it, vi} from 'vitest';
import {IMPERSONATION_STORAGE_KEY} from '@/lib/auth/impersonation';
import {api} from './http';

// Node ships its own (broken, file-backed) localStorage that shadows jsdom's
const storage = new Map<string, string>();
Object.defineProperty(window, 'localStorage', {
    configurable: true,
    value: {
        getItem: (k: string) => storage.get(k) ?? null,
        setItem: (k: string, v: string) => storage.set(k, v),
        removeItem: (k: string) => storage.delete(k),
        clear: () => storage.clear(),
    },
});

vi.mock('@/lib/config/ConfigProvider', () => ({
    getConfig: () => ({apiUrl: 'https://api.test'}),
}));

vi.mock('@/lib/auth/client', () => ({
    getAuthClient: () => ({
        getAccessToken: () => Promise.resolve('admin-token'),
    }),
}));

const fetchMock = vi.fn();
vi.stubGlobal('fetch', fetchMock);

async function sentHeaders(call: () => Promise<unknown>): Promise<Headers> {
    fetchMock.mockResolvedValueOnce(
        new Response('{}', {
            headers: {'Content-Type': 'application/json'},
        })
    );
    await call();

    return (fetchMock.mock.calls.at(-1)![0] as Request).headers;
}

describe('impersonation header', () => {
    beforeEach(() => {
        window.localStorage.clear();
        fetchMock.mockReset();
    });

    it('is not sent without impersonation', async () => {
        const headers = await sentHeaders(() => api.get('/assets'));
        expect(headers.get('Authorization')).toBe('Bearer admin-token');
        expect(headers.has('X-Impersonate-User')).toBe(false);
    });

    it('is sent to the API while impersonating', async () => {
        window.localStorage.setItem(
            IMPERSONATION_STORAGE_KEY,
            JSON.stringify({
                id: 'alice-id',
                username: 'alice',
                roles: [],
                groups: [],
            })
        );

        let headers = await sentHeaders(() => api.get('/assets'));
        expect(headers.get('Authorization')).toBe('Bearer admin-token');
        expect(headers.get('X-Impersonate-User')).toBe('alice-id');

        headers = await sentHeaders(() =>
            api.get('/impersonation/users', {asRealUser: true})
        );
        expect(headers.has('X-Impersonate-User')).toBe(false);

        // Other hosts do not allow the header (CORS)
        headers = await sentHeaders(() => api.get('https://s3.test/file', {}));
        expect(headers.has('X-Impersonate-User')).toBe(false);
    });
});
