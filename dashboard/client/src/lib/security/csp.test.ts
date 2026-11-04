import {describe, expect, it} from 'vitest';
import {buildContentSecurityPolicy} from './csp';

describe('buildContentSecurityPolicy', () => {
    it('only allows the same origin by default', () => {
        expect(buildContentSecurityPolicy({})).toBe(
            "frame-src 'self'; frame-ancestors 'self'"
        );
    });

    it('lets the clients and the API admins of the stack embed the dashboard, and nobody else', () => {
        expect(
            buildContentSecurityPolicy({
                KEYCLOAK_URL: 'https://keycloak.test/auth',
                DATABOX_CLIENT_URL: 'https://databox.test/',
                EXPOSE_CLIENT_URL: 'https://expose.test',
                UPLOADER_CLIENT_URL: 'not an url',
                DATABOX_API_URL: 'https://api-databox.test',
                ALLOWED_FRAME_ANCESTORS:
                    ' https://a.test  https://databox.test ',
            })
        ).toBe(
            "frame-src 'self' https://keycloak.test; frame-ancestors 'self' https://databox.test https://expose.test https://api-databox.test https://a.test"
        );
    });
});
