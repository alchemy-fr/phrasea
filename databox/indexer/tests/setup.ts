/**
 * Runs before every test file is imported.
 *
 * `src/configLoader.ts` reads `${cwd}/config/${CONFIG_FILE}` at module load
 * time, and `locations.ts` / `pathFilter.ts` / `server.ts` call into it at the
 * top level. Every variable the fixture config references must therefore be set
 * here, before any application module is imported.
 */
import {mkdirSync, mkdtempSync, rmSync, writeFileSync} from 'fs';
import {tmpdir} from 'os';
import path from 'path';

process.env.CONFIG_FILE = 'config.test.json';

/**
 * A real directory behind the `fs_served` location, so the asset HTTP server
 * can be exercised end to end. One per test file (vitest forks isolate them).
 */
const servedDir = mkdtempSync(path.join(tmpdir(), 'indexer-served-'));
mkdirSync(path.join(servedDir, 'a'), {recursive: true});
writeFileSync(path.join(servedDir, 'a', 'b.txt'), 'served content');

process.on('exit', () => {
    rmSync(servedDir, {recursive: true, force: true});
});

const env: Record<string, string> = {
    DATABOX_API_URL: 'http://databox-api.test',
    DATABOX_CLIENT_ID: 'test-client',
    DATABOX_CLIENT_SECRET: 'test-secret',
    DATABOX_OWNER_ID: 'owner-1',
    DATABOX_VERIFY_SSL: 'false',
    DATABOX_CONCURRENCY: '2',
    DATABOX_WORKSPACE_SLUG: 'test-workspace',
    PUBLIC_URL: 'http://indexer.test',
    WATCH_DIR: '/fs-watch',
    WATCH_DIR_PREFIX: 'fs',
    WATCH_SOURCE_DIR: '/source',
    SERVED_DIR: servedDir,
    AMQP_DSN: 'amqp://guest:guest@rabbitmq:5672/s3events',
    S3_ENDPOINT: 'http://minio.test:9000',
    S3_ACCESS_KEY: 'access-key',
    S3_SECRET_KEY: 'secret-key',
    BUCKET_NAMES: 'bucket-a,bucket-b',
    ZERO_INT: '0',
    FALSY_BOOL: '0',
};

for (const [k, v] of Object.entries(env)) {
    process.env[k] = v;
}

delete process.env.AN_ENV_VAR_THAT_IS_NOT_SET;
