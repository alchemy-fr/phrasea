import {describe, expect, it} from 'vitest';
import {buildCsp} from '@alchemy/core/security/csp';
import {contentSecuritySources} from './csp';

function policy(env: Record<string, string | undefined>): string {
    return buildCsp({nonce: 'n', ...contentSecuritySources(env)});
}

describe('contentSecuritySources', () => {
    it('only allows the same origin by default, and any embedding site', () => {
        const p = policy({});

        expect(p).toContain("connect-src 'self';");
        expect(p).not.toContain('frame-ancestors');
    });

    it('lets the pages talk to Keycloak, the API and the buckets', () => {
        const p = policy({
            UPLOADER_API_URL: 'https://api-uploader.test',
            KEYCLOAK_URL: 'https://keycloak.test/auth',
            CONFIGURATOR_S3_ENDPOINT: 'https://s3.test',
            MATOMO_URL: 'https://matomo.test',
            DASHBOARD_CLIENT_URL: 'https://dashboard.test',
        });

        expect(p).toContain(
            "connect-src 'self' https://keycloak.test https://matomo.test https://s3.test https://*.s3.test https://api-uploader.test;"
        );
        expect(p).toContain(
            "script-src 'self' 'nonce-n' 'strict-dynamic' https://matomo.test;"
        );
        expect(p).toContain(
            "frame-src 'self' https://keycloak.test https://dashboard.test;"
        );
        expect(p).toMatch(/; form-action 'self' https:\/\/keycloak\.test$/);
    });
});
