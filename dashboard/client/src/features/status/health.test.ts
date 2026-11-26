// @vitest-environment node
import {describe, expect, it, vi} from 'vitest';
import {
    classifyHealthResponse,
    createStatusLoader,
    probeApi,
    probeService,
    probeVersion,
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

describe('probeVersion', () => {
    it('reads the version of the API', async () => {
        const get = vi.fn().mockResolvedValue({
            status: 200,
            body: '{"version":"5.0.0","commit":"abc"}',
        });
        await expect(
            probeVersion('https://api.test', {get, verifySsl: false})
        ).resolves.toBe('5.0.0');
        expect(get).toHaveBeenCalledWith('https://api.test/_health/version', {
            headers: {Accept: 'application/json'},
            rejectUnauthorized: false,
            timeout: PROBE_TIMEOUT_MS,
        });
    });

    it('leaves the version out when it cannot be read', async () => {
        for (const answer of [
            {status: 404, body: '{"version":"5.0.0"}'},
            {status: 200, body: '<html>not the API</html>'},
            {status: 200, body: '{"version":""}'},
            {status: 200, body: 'null'},
        ]) {
            const get = vi.fn().mockResolvedValue(answer);
            await expect(
                probeVersion('https://api.test', {get})
            ).resolves.toBeUndefined();
        }
        const get = vi.fn().mockRejectedValue(new Error('ECONNREFUSED'));
        await expect(
            probeVersion('https://api.test', {get})
        ).resolves.toBeUndefined();
    });
});

describe('probeService', () => {
    it('reads the status and the version together', async () => {
        const get = vi.fn(async (url: string) =>
            url.endsWith('/_health/version')
                ? {status: 200, body: '{"version":"next@34886e8"}'}
                : {status: 200, body: '{}'}
        );
        await expect(probeService('https://api.test', {get})).resolves.toEqual({
            status: 'up',
            version: 'next@34886e8',
        });
    });

    it('keeps the status when the version fails', async () => {
        const get = vi.fn(async (url: string) =>
            url.endsWith('/_health/version')
                ? {status: 500, body: ''}
                : {status: 503, body: '{"db":{"ok":false}}'}
        );
        const state = await probeService('https://api.test', {get});
        expect(state).toEqual({status: 'degraded'});
        expect(state).not.toHaveProperty('version');
    });

    it('reports a stopped API as down, without a version', async () => {
        const get = vi.fn().mockRejectedValue(new Error('ECONNREFUSED'));
        await expect(probeService('https://api.test', {get})).resolves.toEqual({
            status: 'down',
        });
    });
});

describe('createStatusLoader', () => {
    it('probes the deployed APIs and shares the answer for a while', async () => {
        let time = 0;
        const get = vi.fn(async (url: string) => {
            if (!url.startsWith('https://api-databox.test')) {
                return {status: 502, body: ''};
            }

            return url.endsWith('/_health/version')
                ? {status: 200, body: '{"version":"5.0.0","commit":"abc"}'}
                : {status: 200, body: '{}'};
        });
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
        expect(first).toEqual({
            databox: {status: 'up', version: '5.0.0'},
            uploader: {status: 'down'},
        });
        expect(concurrent).toBe(first);
        expect(get).toHaveBeenCalledTimes(4);
        expect(get.mock.calls.map(([url]) => url)).toEqual(
            expect.arrayContaining([
                'https://api-databox.test/_health/check',
                'https://api-databox.test/_health/version',
            ])
        );

        time = 9_000;
        await load();
        expect(get).toHaveBeenCalledTimes(4);

        time = 11_000;
        await load();
        expect(get).toHaveBeenCalledTimes(8);
    });
});
