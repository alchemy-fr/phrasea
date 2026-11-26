import {describe, expect, it} from 'vitest';
import {buildCsp, pickDirectives} from '@alchemy/core/security/csp';
import {contentSecuritySources, ENFORCED_DIRECTIVES} from './csp';

function policy(env: Record<string, string | undefined>): string {
    return buildCsp({nonce: 'n', ...contentSecuritySources(env)});
}

describe('contentSecuritySources', () => {
    it('only allows the same origin by default', () => {
        expect(pickDirectives(policy({}), ENFORCED_DIRECTIVES)).toBe(
            "frame-src 'self'; frame-ancestors 'self'"
        );
        expect(policy({})).toContain("connect-src 'self';");
    });

    it('allows Keycloak, the dashboard and the configured ancestors', () => {
        expect(
            pickDirectives(
                policy({
                    KEYCLOAK_URL: 'https://keycloak.test/auth',
                    DASHBOARD_CLIENT_URL: 'https://dashboard.test',
                    ALLOWED_FRAME_ANCESTORS: ' https://a.test  https://b.test ',
                }),
                ENFORCED_DIRECTIVES
            )
        ).toBe(
            "frame-src 'self' https://keycloak.test https://dashboard.test; frame-ancestors 'self' https://a.test https://b.test"
        );
    });

    it('lets the pages talk to the API and load its files', () => {
        const p = policy({
            EXPOSE_API_URL: 'https://api-expose.test/',
            KEYCLOAK_URL: 'https://keycloak.test',
            CONFIGURATOR_S3_ENDPOINT: 'https://minio.test',
            CONFIGURATOR_S3_USE_PATH_STYLE_ENDPOINT: 'true',
        });

        expect(p).toContain(
            "connect-src 'self' https://keycloak.test https://minio.test https://api-expose.test;"
        );
        expect(p).toContain(
            "media-src 'self' data: blob: https://minio.test https://api-expose.test;"
        );
    });
});
