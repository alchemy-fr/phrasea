// @vitest-environment node
import {describe, expect, it, vi} from 'vitest';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import {
    bucketConfigLocation,
    bucketRequestHeaders,
    createStackConfigLoader,
    fetchStackConfigFromBucket,
    readStackConfigFile,
    signS3Get,
} from './stackConfig';

const env = {
    CONFIGURATOR_S3_ENDPOINT: 'https://minio.phrasea.local',
    CONFIGURATOR_S3_BUCKET_NAME: 'static',
    CONFIGURATOR_S3_PATH_PREFIX: 'test-prefix/',
    CONFIGURATOR_S3_USE_PATH_STYLE_ENDPOINT: 'true',
    VERIFY_SSL: 'false',
};

describe('stack configuration', () => {
    it('locates config.json like get-config.sh', () => {
        expect(bucketConfigLocation(env)).toEqual({
            url: 'https://minio.phrasea.local/static/test-prefix/config.json',
            host: 'minio.phrasea.local',
            resource: '/static/test-prefix/config.json',
        });
        expect(
            bucketConfigLocation({
                ...env,
                CONFIGURATOR_S3_USE_PATH_STYLE_ENDPOINT: 'false',
                CONFIGURATOR_S3_PATH_PREFIX: '',
            })
        ).toEqual({
            url: 'https://static.minio.phrasea.local/config.json',
            host: 'static.minio.phrasea.local',
            resource: '/static/config.json',
        });
        expect(bucketConfigLocation({})).toBeNull();
    });

    it('signs the request only when asked to, like get-config.sh', () => {
        const location = bucketConfigLocation(env)!;
        expect(bucketRequestHeaders(env, location)).toEqual({});

        const headers = bucketRequestHeaders(
            {
                ...env,
                CONFIG_IS_PUBLIC: 'true',
                CONFIGURATOR_S3_ACCESS_KEY: 'AKIA',
                CONFIGURATOR_S3_SECRET_KEY: 'secret',
            },
            location,
            new Date('2026-09-21T10:00:00Z')
        );
        expect(headers.Host).toBe('minio.phrasea.local');
        expect(headers.Date).toBe('Mon, 21 Sep 2026 10:00:00 GMT');
        expect(headers.Authorization).toMatch(/^AWS AKIA:[A-Za-z0-9+/=]+$/);
        expect(
            signS3Get(location, 'AKIA', 'secret', headers.Date).Authorization
        ).toBe(headers.Authorization);
    });

    it('fetches and parses config.json, treating a missing file as empty', async () => {
        const get = vi.fn().mockResolvedValue({
            status: 200,
            body: '{"databox":{"theme":"{}"}}',
        });
        await expect(fetchStackConfigFromBucket(env, get)).resolves.toEqual({
            databox: {theme: '{}'},
        });
        expect(get).toHaveBeenCalledWith(
            'https://minio.phrasea.local/static/test-prefix/config.json',
            {headers: {}, rejectUnauthorized: false}
        );

        get.mockResolvedValue({status: 404, body: ''});
        await expect(fetchStackConfigFromBucket(env, get)).resolves.toEqual({});

        get.mockResolvedValue({status: 200, body: '[]'});
        await expect(fetchStackConfigFromBucket(env, get)).resolves.toEqual({});

        get.mockResolvedValue({status: 500, body: ''});
        await expect(fetchStackConfigFromBucket(env, get)).rejects.toThrow(
            'HTTP 500'
        );
        await expect(fetchStackConfigFromBucket({}, get)).resolves.toBeNull();
    });

    it('reads the file fetched at container start', async () => {
        const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'stack-config-'));
        const file = path.join(dir, 'stack-config.json');
        await expect(readStackConfigFile(file)).resolves.toBeNull();
        await fs.writeFile(file, '{"logo":{"src":"x"}}');
        await expect(readStackConfigFile(file)).resolves.toEqual({
            logo: {src: 'x'},
        });
    });

    it('caches the configuration and falls back to the file, then to the last one', async () => {
        const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'stack-config-'));
        const file = path.join(dir, 'stack-config.json');
        await fs.writeFile(file, '{"from":"file"}');
        let time = 0;
        const get = vi
            .fn()
            .mockResolvedValueOnce({status: 200, body: '{"from":"bucket"}'})
            .mockRejectedValueOnce(new Error('down'))
            .mockRejectedValueOnce(new Error('down'));
        const log = vi.fn();
        const load = createStackConfigLoader({
            env: {
                ...env,
                STACK_CONFIG_SRC: file,
                STACK_CONFIG_REFRESH_INTERVAL: '30',
            },
            get,
            now: () => time,
            log,
        });

        await expect(load()).resolves.toEqual({from: 'bucket'});
        await expect(load()).resolves.toEqual({from: 'bucket'});
        expect(get).toHaveBeenCalledTimes(1);

        time = 31_000;
        await expect(load()).resolves.toEqual({from: 'file'});
        expect(log).toHaveBeenCalledTimes(1);

        time = 62_000;
        await fs.rm(file);
        await expect(load()).resolves.toEqual({from: 'file'});
        expect(get).toHaveBeenCalledTimes(3);
    });

    it('reads the file only when the bucket is not configured', async () => {
        const get = vi.fn();
        const load = createStackConfigLoader({
            env: {STACK_CONFIG_SRC: '/nonexistent/stack-config.json'},
            get,
        });
        await expect(load()).resolves.toEqual({});
        expect(get).not.toHaveBeenCalled();
    });
});
