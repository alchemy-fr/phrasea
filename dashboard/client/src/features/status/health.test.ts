// @vitest-environment node
import {describe, expect, it, vi} from 'vitest';
import {
    classifyHealthResponse,
    createStatusLoader,
    probeApi,
    PROBE_TIMEOUT_MS,
} from './health';

describe('classifyHealthResponse', () => {
    it('tells a failing check from a stopped API', () => {
        expect(classifyHealthResponse(200, '{}')).toBe('up');
        expect(classifyHealthResponse(401, '')).toBe('up');
        expect(classifyHealthResponse(503, '{"db":{"ok":false}}')).toBe(
            'degraded'
        );
        expect(classifyHealthResponse(503, 'Service Unavailable')).toBe('down');
        expect(classifyHealthResponse(502, 'Bad Gateway')).toBe('down');
        expect(classifyHealthResponse(404, '404 page not found')).toBe('down');
    });
});

describe('probeApi', () => {
    it('calls the health check of the API', async () => {
        const get = vi.fn().mockResolvedValue({status: 200, body: '{}'});
        await expect(
            probeApi('https://api.test', {get, verifySsl: false})
        ).resolves.toBe('up');
        expect(get).toHaveBeenCalledWith('https://api.test/_health/check', {
            headers: {Accept: 'application/json'},
            rejectUnauthorized: false,
            timeout: PROBE_TIMEOUT_MS,
        });
    });

    it('reports an unreachable API as down', async () => {
        const get = vi.fn().mockRejectedValue(new Error('ECONNREFUSED'));
        await expect(probeApi('https://api.test', {get})).resolves.toBe('down');
    });
});

describe('createStatusLoader', () => {
    it('probes the deployed APIs and shares the answer for a while', async () => {
        let time = 0;
        const get = vi.fn(async (url: string) =>
            url.startsWith('https://api-databox.test')
                ? {status: 200, body: '{}'}
                : {status: 502, body: ''}
        );
        const load = createStatusLoader({
            env: {
                DATABOX_API_URL: 'https://api-databox.test/',
                UPLOADER_API_URL: 'https://api-uploader.test',
                STATUS_TTL: '10',
            },
            get,
            now: () => time,
        });

        const [first, concurrent] = await Promise.all([load(), load()]);
        expect(first).toEqual({databox: 'up', uploader: 'down'});
        expect(concurrent).toBe(first);
        expect(get).toHaveBeenCalledTimes(2);
        expect(get.mock.calls[0][0]).toBe(
            'https://api-databox.test/_health/check'
        );

        time = 9_000;
        await load();
        expect(get).toHaveBeenCalledTimes(2);

        time = 11_000;
        await load();
        expect(get).toHaveBeenCalledTimes(4);
    });
});
