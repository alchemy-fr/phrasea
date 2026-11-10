import {describe, expect, it} from 'vitest';
import {sentryClientConfigFromEnv} from './config';

describe('sentryClientConfigFromEnv', () => {
    it('reads the DSN, the environment and the release', () => {
        expect(
            sentryClientConfigFromEnv({
                SENTRY_DSN: 'https://public@o1.ingest.sentry.io/42',
                SENTRY_ENVIRONMENT: 'staging',
                SENTRY_RELEASE: '1.2.3',
            })
        ).toEqual({
            dsn: 'https://public@o1.ingest.sentry.io/42',
            environment: 'staging',
            release: '1.2.3',
        });
    });

    it('is undefined without DSN', () => {
        expect(sentryClientConfigFromEnv({})).toBeUndefined();
        expect(
            sentryClientConfigFromEnv({SENTRY_DSN: '', SENTRY_RELEASE: '1'})
        ).toBeUndefined();
    });
});
