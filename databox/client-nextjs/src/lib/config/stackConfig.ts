import 'server-only';
import fs from 'node:fs/promises';
import http from 'node:http';
import https from 'node:https';
import crypto from 'node:crypto';

/**
 * The stack configuration: the configurator entries (logo, organisation
 * theme…) that the API dumps to `config.json` in the static bucket. The other
 * clients fetch it once at container start (lib/bash/configurator/
 * get-config.sh) into /etc/app/stack-config.json; this server reads that file
 * when present and, when the bucket is configured, refreshes it from the
 * bucket every `STACK_CONFIG_REFRESH_INTERVAL` seconds so that changes made
 * by an administrator do not need a restart.
 */
export type StackConfig = Record<string, unknown>;

export type StackConfigEnv = Record<string, string | undefined>;

export const DEFAULT_STACK_CONFIG_SRC = '/etc/app/stack-config.json';
export const DEFAULT_REFRESH_INTERVAL = 60;

function isTrue(value: string | undefined): boolean {
    return ['1', 'true', 'on', 'y', 'yes'].includes(
        (value ?? '').toLowerCase()
    );
}

function isFalse(value: string | undefined): boolean {
    return ['0', 'false', 'off', 'n', 'no'].includes(
        (value ?? '').toLowerCase()
    );
}

export type BucketLocation = {
    url: string;
    host: string;
    /** `/<bucket>/<prefix>config.json`, signed when the config is not public */
    resource: string;
};

/**
 * Where `config.json` lives, built like get-config.sh does from the
 * `CONFIGURATOR_S3_*` variables. Null when the bucket is not configured.
 */
export function bucketConfigLocation(
    env: StackConfigEnv
): BucketLocation | null {
    const bucket = env.CONFIGURATOR_S3_BUCKET_NAME?.trim();
    const endpoint = env.CONFIGURATOR_S3_ENDPOINT?.trim();
    if (!bucket || !endpoint) {
        return null;
    }
    const prefix = env.CONFIGURATOR_S3_PATH_PREFIX ?? '';
    const pathStyle = isTrue(env.CONFIGURATOR_S3_USE_PATH_STYLE_ENDPOINT);
    const endpointHost = endpoint
        .replace(/^https?:\/\//, '')
        .replace(/\/+$/, '');
    const host = pathStyle ? endpointHost : `${bucket}.${endpointHost}`;
    const scheme = endpoint.startsWith('http://') ? 'http' : 'https';
    const path = pathStyle
        ? `/${bucket}/${prefix}config.json`
        : `/${prefix}config.json`;

    return {
        url: `${scheme}://${host}${path}`,
        host,
        resource: `/${bucket}/${prefix}config.json`,
    };
}

/**
 * AWS signature version 2 of a GET, as get-config.sh computes it with
 * openssl when the configuration is not public.
 */
export function signS3Get(
    location: BucketLocation,
    accessKey: string,
    secretKey: string,
    date: string,
    contentType = 'binary/octet-stream'
): Record<string, string> {
    const stringToSign = `GET\n\n${contentType}\n${date}\n${location.resource}`;
    const signature = crypto
        .createHmac('sha1', secretKey)
        .update(stringToSign)
        .digest('base64');

    return {
        'Host': location.host,
        'Date': date,
        'Content-Type': contentType,
        'Authorization': `AWS ${accessKey}:${signature}`,
    };
}

/** Headers of the request fetching `config.json`, mirroring get-config.sh */
export function bucketRequestHeaders(
    env: StackConfigEnv,
    location: BucketLocation,
    now: Date = new Date()
): Record<string, string> {
    // get-config.sh signs the request when CONFIG_IS_PUBLIC is set (and
    // fetches anonymously otherwise): same variable, same behaviour.
    if (
        isTrue(env.CONFIG_IS_PUBLIC) &&
        env.CONFIGURATOR_S3_ACCESS_KEY &&
        env.CONFIGURATOR_S3_SECRET_KEY
    ) {
        return signS3Get(
            location,
            env.CONFIGURATOR_S3_ACCESS_KEY,
            env.CONFIGURATOR_S3_SECRET_KEY,
            now.toUTCString()
        );
    }

    return {};
}

export type HttpGet = (
    url: string,
    options: {headers: Record<string, string>; rejectUnauthorized: boolean}
) => Promise<{status: number; body: string}>;

const httpGet: HttpGet = (url, {headers, rejectUnauthorized}) =>
    new Promise((resolve, reject) => {
        const client = url.startsWith('http://') ? http : https;
        const req = client.get(
            url,
            {headers, rejectUnauthorized, timeout: 10_000},
            res => {
                const chunks: Buffer[] = [];
                res.on('data', (c: Buffer) => chunks.push(c));
                res.on('end', () =>
                    resolve({
                        status: res.statusCode ?? 0,
                        body: Buffer.concat(chunks).toString('utf8'),
                    })
                );
                res.on('error', reject);
            }
        );
        req.on('timeout', () => req.destroy(new Error('timeout')));
        req.on('error', reject);
    });

function parse(body: string, source: string): StackConfig {
    const data: unknown = JSON.parse(body);
    if (!data || typeof data !== 'object' || Array.isArray(data)) {
        if (Array.isArray(data) && data.length === 0) {
            // An empty configuration is dumped as `[]` by PHP
            return {};
        }
        throw new Error(`${source}: not a JSON object`);
    }

    return data as StackConfig;
}

export async function fetchStackConfigFromBucket(
    env: StackConfigEnv,
    get: HttpGet = httpGet
): Promise<StackConfig | null> {
    const location = bucketConfigLocation(env);
    if (!location) {
        return null;
    }
    const {status, body} = await get(location.url, {
        headers: bucketRequestHeaders(env, location),
        rejectUnauthorized: !isFalse(env.VERIFY_SSL),
    });
    if (status === 404) {
        return {};
    }
    if (status !== 200) {
        throw new Error(`${location.url}: HTTP ${status}`);
    }

    return parse(body, location.url);
}

export async function readStackConfigFile(
    path: string
): Promise<StackConfig | null> {
    let body: string;
    try {
        body = await fs.readFile(path, 'utf8');
    } catch (e) {
        if ((e as NodeJS.ErrnoException).code === 'ENOENT') {
            return null;
        }
        throw e;
    }

    return parse(body, path);
}

export type StackConfigLoader = () => Promise<StackConfig>;

/**
 * A cached loader: the bucket when configured (falling back to the file, then
 * to the last known configuration, when it cannot be reached), the file
 * otherwise; refreshed at most every `refreshInterval` seconds.
 */
export function createStackConfigLoader({
    env = process.env,
    get = httpGet,
    now = () => Date.now(),
    log = (message: string) => console.warn(`[stack-config] ${message}`),
}: {
    env?: StackConfigEnv;
    get?: HttpGet;
    now?: () => number;
    log?: (message: string) => void;
} = {}): StackConfigLoader {
    let cached: StackConfig | undefined;
    let expiresAt = 0;
    let pending: Promise<StackConfig> | undefined;

    const load = async (): Promise<StackConfig> => {
        const path = env.STACK_CONFIG_SRC || DEFAULT_STACK_CONFIG_SRC;
        let config: StackConfig | null = null;
        if (bucketConfigLocation(env)) {
            try {
                config = await fetchStackConfigFromBucket(env, get);
            } catch (e) {
                log(
                    `cannot fetch the configuration from the bucket: ${(e as Error).message}`
                );
            }
        }
        if (!config) {
            try {
                config = await readStackConfigFile(path);
            } catch (e) {
                log(`cannot read ${path}: ${(e as Error).message}`);
            }
        }

        return config ?? cached ?? {};
    };

    return async () => {
        if (cached && now() < expiresAt) {
            return cached;
        }
        pending ??= load()
            .then(config => {
                cached = config;
                const interval = Number.parseInt(
                    env.STACK_CONFIG_REFRESH_INTERVAL ?? '',
                    10
                );
                expiresAt =
                    now() +
                    (Number.isNaN(interval)
                        ? DEFAULT_REFRESH_INTERVAL
                        : interval) *
                        1000;

                return config;
            })
            .finally(() => {
                pending = undefined;
            });

        return pending;
    };
}

let loader: StackConfigLoader | undefined;

/** The stack configuration, for server components and route handlers */
export function getStackConfig(): Promise<StackConfig> {
    return (loader ??= createStackConfigLoader())();
}
