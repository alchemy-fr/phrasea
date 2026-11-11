import {describe, expect, it} from 'vitest';
import {sentryClientConfigFromEnv} from './config';

describe('sentryClientConfigFromEnv', () => {
    it('reads the environment and the release, never the DSN', () => {
        const config = sentryClientConfigFromEnv({
            SENTRY_DSN: 'https://public@o1.ingest.sentry.io/42',
            SENTRY_ENVIRONMENT: 'staging',
            SENTRY_RELEASE: '1.2.3',
        });

        expect(config).toEqual({
            environment: 'staging',
            release: '1.2.3',
        });
        expect(JSON.stringify(config)).not.toContain('ingest');
    });

    it('is undefined without a valid DSN', () => {
        expect(sentryClientConfigFromEnv({})).toBeUndefined();
        expect(
            sentryClientConfigFromEnv({SENTRY_DSN: '', SENTRY_RELEASE: '1'})
        ).toBeUndefined();
        expect(
            sentryClientConfigFromEnv({SENTRY_DSN: 'nope', SENTRY_RELEASE: '1'})
        ).toBeUndefined();
    });
});
