/**
 * Environment of the suite.
 *
 * Deliberately not src/env.ts: getEnvStrict() calls process.exit(1), which a
 * test runner turns into an unexplained crash instead of a failed assertion.
 */

export function env(name: string, fallback?: string): string {
    const value = process.env[name] || fallback;

    if (!value) {
        throw new Error(
            `Missing ${name}. It is set by bin/dev/test-indexer-e2e.sh ` +
                'or by the databox-indexer service in docker-compose.yml.'
        );
    }

    return value;
}

export const SLUG = env('INDEXER_E2E_SLUG', 'indexer-e2e');
export const API_URL = env('DATABOX_API_URL');
export const CLIENT_ID = env('DATABOX_CLIENT_ID');
export const CLIENT_SECRET = env('DATABOX_CLIENT_SECRET');
