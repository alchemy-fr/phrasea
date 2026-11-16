import {describe, expect, it} from 'vitest';
import {buildCsp, pickDirectives} from '@alchemy/next-core/security/csp';
import {contentSecuritySources, ENFORCED_DIRECTIVES} from './csp';

function policy(env: Record<string, string | undefined>): string {
    return buildCsp({nonce: 'n', ...contentSecuritySources(env)});
}

describe('contentSecuritySources', () => {
    it('only allows the same origin by default', () => {
        expect(pickDirectives(policy({}), ENFORCED_DIRECTIVES)).toBe(
            "frame-src 'self'; frame-ancestors 'self'"
        );
    });

    it('lets the clients and the API admins of the stack embed the dashboard, and nobody else', () => {
        expect(
            pickDirectives(
                policy({
                    KEYCLOAK_URL: 'https://keycloak.test/auth',
                    DATABOX_CLIENT_URL: 'https://databox.test/',
                    EXPOSE_CLIENT_URL: 'https://expose.test',
                    UPLOADER_CLIENT_URL: 'not an url',
                    DATABOX_API_URL: 'https://api-databox.test',
                    ALLOWED_FRAME_ANCESTORS:
                        ' https://a.test  https://databox.test ',
                }),
                ENFORCED_DIRECTIVES
            )
        ).toBe(
            "frame-src 'self' https://keycloak.test; frame-ancestors 'self' https://databox.test https://expose.test https://api-databox.test https://a.test"
        );
    });

    it('lets the pages talk to Keycloak and the APIs', () => {
        expect(
            policy({
                KEYCLOAK_URL: 'https://keycloak.test/auth',
                DATABOX_API_URL: 'https://api-databox.test',
                UPLOADER_API_URL: 'https://api-uploader.test',
            })
        ).toContain(
            "connect-src 'self' https://keycloak.test https://api-databox.test https://api-uploader.test;"
        );
    });
});
